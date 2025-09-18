<?php
header('Content-Type: application/json');

const ALLOWED_ACTIONS = [
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
];

$action = $_GET['action'] ?? '';

if (!in_array($action, ALLOWED_ACTIONS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing action.']);
    exit;
}

try {
    switch ($action) {
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
    }
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}

function respondWithSuccess(array $data): void
{
    $data['success'] = $data['success'] ?? true;
    echo json_encode($data);
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
