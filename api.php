<?php
session_start();
header('Content-Type: application/json');

const CREDENTIALS_FILE = __DIR__ . '/data/credentials.json';
const ALLOWED_ACTIONS = [
    'session',
    'login',
    'logout',
    'update_credentials',
    'list_containers',
    'start_container',
    'stop_container',
    'restart_container',
    'remove_container',
    'list_images',
    'remove_image',
    'list_volumes',
    'remove_volume',
    'list_networks',
    'remove_network',
    'create_container',
    'container_logs',
    'container_exec',
];

$action = $_GET['action'] ?? '';
if (!in_array($action, ALLOWED_ACTIONS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing action.']);
    exit;
}

$publicActions = ['session', 'login'];

try {
    ensureCredentialStore();
    if (!in_array($action, $publicActions, true)) {
        requireAuth();
    }

    switch ($action) {
        case 'session':
            respondWithSuccess(getSessionDetails());
            break;
        case 'login':
            ensurePost();
            respondWithSuccess(handleLogin());
            break;
        case 'logout':
            ensurePost();
            respondWithSuccess(handleLogout());
            break;
        case 'update_credentials':
            ensurePost();
            respondWithSuccess(updateCredentials());
            break;
        case 'list_containers':
            respondWithSuccess(['containers' => listContainers()]);
            break;
        case 'start_container':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('start', requireId()));
            break;
        case 'stop_container':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('stop', requireId()));
            break;
        case 'restart_container':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('restart', requireId()));
            break;
        case 'remove_container':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('rm', requireId(), ['-f']));
            break;
        case 'list_images':
            respondWithSuccess(['images' => listImages()]);
            break;
        case 'remove_image':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('rmi', requireId()));
            break;
        case 'list_volumes':
            respondWithSuccess(['volumes' => listVolumes()]);
            break;
        case 'remove_volume':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('volume rm', requireId()));
            break;
        case 'list_networks':
            respondWithSuccess(['networks' => listNetworks()]);
            break;
        case 'remove_network':
            ensurePost();
            respondWithSuccess(runSimpleDockerCommand('network rm', requireId()));
            break;
        case 'create_container':
            ensurePost();
            respondWithSuccess(createContainer());
            break;
        case 'container_logs':
            respondWithSuccess(fetchLogs());
            break;
        case 'container_exec':
            ensurePost();
            respondWithSuccess(runContainerCommand());
            break;
    }
} catch (RuntimeException $exception) {
    $code = http_response_code();
    if ($code === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}

function respondWithSuccess(array $data): void
{
    if (!array_key_exists('success', $data)) {
        $data['success'] = true;
    }
    echo json_encode($data);
}

function ensureCredentialStore(): void
{
    $directory = dirname(CREDENTIALS_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to initialise credential storage.');
    }

    if (!file_exists(CREDENTIALS_FILE)) {
        $credentials = [
            'username' => 'admin',
            'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
            'must_change_password' => true,
        ];
        if (file_put_contents(CREDENTIALS_FILE, json_encode($credentials, JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException('Unable to write default credentials.');
        }
    }
}

function loadCredentials(): array
{
    $contents = @file_get_contents(CREDENTIALS_FILE);
    if ($contents === false) {
        throw new RuntimeException('Unable to load credentials.');
    }

    $data = json_decode($contents, true);
    if (!is_array($data) || !isset($data['username'], $data['password_hash'])) {
        throw new RuntimeException('Credential file is corrupted.');
    }

    $data['must_change_password'] = !empty($data['must_change_password']);

    return $data;
}

function saveCredentials(array $credentials): void
{
    if (file_put_contents(CREDENTIALS_FILE, json_encode($credentials, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Unable to persist credentials.');
    }
}

function getSessionDetails(): array
{
    $authenticated = !empty($_SESSION['authenticated']);
    $details = ['authenticated' => $authenticated];

    if ($authenticated) {
        $credentials = loadCredentials();
        $details['username'] = $_SESSION['username'] ?? $credentials['username'];
        $details['mustChangePassword'] = !empty($credentials['must_change_password']);
    }

    return $details;
}

function handleLogin(): array
{
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        throw new RuntimeException('Username and password are required.');
    }

    $credentials = loadCredentials();

    if (!hash_equals($credentials['username'], $username) || !password_verify($password, $credentials['password_hash'])) {
        throw new RuntimeException('Invalid credentials.');
    }

    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $credentials['username'];

    return [
        'message' => 'Login successful.',
        'username' => $credentials['username'],
        'mustChangePassword' => !empty($credentials['must_change_password']),
    ];
}

function handleLogout(): array
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    return ['message' => 'Logged out successfully.'];
}

function updateCredentials(): array
{
    $credentials = loadCredentials();

    $username = trim($_POST['username'] ?? '');
    $currentPassword = (string) ($_POST['currentPassword'] ?? '');
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if ($username === '') {
        throw new RuntimeException('A username is required.');
    }

    if ($currentPassword === '' || !password_verify($currentPassword, $credentials['password_hash'])) {
        throw new RuntimeException('Current password is incorrect.');
    }

    $mustChange = !empty($credentials['must_change_password']);
    $passwordHash = $credentials['password_hash'];

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            throw new RuntimeException('New password must be at least 8 characters long.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New passwords do not match.');
        }
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $mustChange = false;
    } elseif ($mustChange) {
        throw new RuntimeException('A new password must be provided.');
    }

    $updated = [
        'username' => $username,
        'password_hash' => $passwordHash,
        'must_change_password' => $mustChange,
    ];

    saveCredentials($updated);
    $_SESSION['username'] = $username;

    return [
        'message' => 'Credentials updated successfully.',
        'username' => $username,
        'mustChangePassword' => $updated['must_change_password'],
    ];
}

function requireAuth(): void
{
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        throw new RuntimeException('Authentication required.');
    }
}

function runSimpleDockerCommand(string $command, string $identifier, array $flags = []): array
{
    $parts = array_merge(['docker'], preg_split('/\s+/', trim($command)), $flags, [$identifier]);
    $result = runCommand($parts);
    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Docker command failed.');
    }

    return [
        'message' => trim($result['output']) ?: 'Command executed successfully.',
    ];
}

function runContainerCommand(): array
{
    $id = requireId();
    $command = trim($_POST['command'] ?? '');
    if ($command === '') {
        throw new RuntimeException('A command is required.');
    }

    $parts = ['docker', 'exec', '-i', $id, 'sh', '-lc', $command];
    $result = runCommand($parts);
    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to run command.');
    }

    return [
        'output' => trim($result['output']) ?: 'Command executed successfully.',
    ];
}

function listContainers(): array
{
    $command = "docker ps -a --format '{{json .}}'";
    $result = runCommand($command);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to list containers.');
    }

    $containers = [];
    foreach (array_filter(explode("\n", trim($result['output']))) as $line) {
        $data = json_decode($line, true);
        if (!$data) {
            continue;
        }
        $containers[] = [
            'id' => $data['ID'] ?? '',
            'name' => $data['Names'] ?? '',
            'image' => $data['Image'] ?? '',
            'status' => $data['Status'] ?? '',
            'ports' => $data['Ports'] ?? '',
            'created' => $data['RunningFor'] ?? '',
        ];
    }

    return $containers;
}

function listImages(): array
{
    $command = "docker images --format '{{json .}}'";
    $result = runCommand($command);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to list images.');
    }

    $images = [];
    foreach (array_filter(explode("\n", trim($result['output']))) as $line) {
        $data = json_decode($line, true);
        if (!$data) {
            continue;
        }
        $images[] = [
            'repository' => $data['Repository'] ?? '',
            'tag' => $data['Tag'] ?? '',
            'id' => $data['ID'] ?? '',
            'size' => $data['Size'] ?? '',
            'created' => $data['CreatedSince'] ?? '',
        ];
    }

    return $images;
}

function listVolumes(): array
{
    $command = "docker volume ls --format '{{json .}}'";
    $result = runCommand($command);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to list volumes.');
    }

    $volumes = [];
    foreach (array_filter(explode("\n", trim($result['output']))) as $line) {
        $data = json_decode($line, true);
        if (!$data) {
            continue;
        }
        $volumes[] = [
            'name' => $data['Name'] ?? '',
            'driver' => $data['Driver'] ?? '',
            'mountpoint' => $data['Mountpoint'] ?? '',
        ];
    }

    return $volumes;
}

function listNetworks(): array
{
    $command = "docker network ls --format '{{json .}}'";
    $result = runCommand($command);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to list networks.');
    }

    $networks = [];
    foreach (array_filter(explode("\n", trim($result['output']))) as $line) {
        $data = json_decode($line, true);
        if (!$data) {
            continue;
        }
        $networks[] = [
            'id' => $data['ID'] ?? '',
            'name' => $data['Name'] ?? '',
            'driver' => $data['Driver'] ?? '',
            'scope' => $data['Scope'] ?? '',
        ];
    }

    return $networks;
}

function createContainer(): array
{
    $name = trim($_POST['name'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $command = trim($_POST['command'] ?? '');
    $ports = array_filter(array_map('trim', explode(',', $_POST['ports'] ?? '')));
    $env = array_filter(array_map('trim', explode(',', $_POST['env'] ?? '')));
    $volumes = array_filter(array_map('trim', explode(',', $_POST['volumes'] ?? '')));

    if ($name === '' || $image === '') {
        throw new RuntimeException('Container name and image are required.');
    }

    $parts = ['docker', 'run', '-d', '--name', $name];

    foreach ($ports as $mapping) {
        $parts[] = '-p';
        $parts[] = $mapping;
    }

    foreach ($env as $item) {
        if ($item === '') {
            continue;
        }
        $parts[] = '-e';
        $parts[] = $item;
    }

    foreach ($volumes as $volume) {
        if ($volume === '') {
            continue;
        }
        $parts[] = '-v';
        $parts[] = $volume;
    }

    $parts[] = $image;

    if ($command !== '') {
        foreach (preg_split('/\s+/', $command) as $cmdPart) {
            if ($cmdPart === '') {
                continue;
            }
            $parts[] = $cmdPart;
        }
    }

    $result = runCommand($parts);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Failed to create container.');
    }

    return [
        'message' => 'Container created successfully.',
        'containerId' => trim($result['output']),
    ];
}

function fetchLogs(): array
{
    $id = requireId();
    $tail = isset($_GET['tail']) ? (int) $_GET['tail'] : 100;
    if ($tail <= 0) {
        $tail = 100;
    }

    $parts = ['docker', 'logs', '--tail', (string) $tail, $id];
    $result = runCommand($parts);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($result['error'] ?: 'Unable to fetch logs.');
    }

    return [
        'logs' => $result['output'],
    ];
}

function requireId(): string
{
    $id = trim($_POST['id'] ?? $_GET['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException('A resource identifier is required.');
    }
    return $id;
}

function ensurePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('POST method required.');
    }
}

/**
 * @param string|array $command
 */
function runCommand($command): array
{
    if (is_array($command)) {
        $commandString = buildCommand($command);
    } else {
        $commandString = $command;
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($commandString, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        return [
            'exitCode' => 1,
            'output' => '',
            'error' => 'Unable to execute command.',
        ];
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'output' => $output,
        'error' => $error,
    ];
}

function buildCommand(array $parts): string
{
    $escaped = array_map(static function ($part) {
        if ($part === '') {
            return "''";
        }
        if (preg_match('/^-[A-Za-z0-9_-]+$/', $part)) {
            return $part;
        }
        if (preg_match('/^[A-Za-z0-9._:\\/-]+$/', $part)) {
            return $part;
        }
        return escapeshellarg($part);
    }, $parts);

    return implode(' ', $escaped);
}
