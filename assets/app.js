const apiBase = 'api.php';

const state = {
    containers: [],
    images: [],
    volumes: [],
    networks: [],
    session: { authenticated: false, username: 'admin', mustChangePassword: false },
    currentView: 'containers',
    theme: 'light',
};

const VIEW_TEXT = {
    containers: {
        title: 'Containers',
        subtitle: 'Manage running Docker workloads with guided controls.',
    },
    deploy: {
        title: 'Deploy container',
        subtitle: 'Use the dropdown driven form to launch services without the CLI.',
    },
    logs: {
        title: 'Container logs',
        subtitle: 'Inspect logs by selecting a container and tail depth from dropdowns.',
    },
    console: {
        title: 'Interactive console',
        subtitle: 'Run curated commands or a full bash shell inside a container.',
    },
    images: {
        title: 'Images',
        subtitle: 'Review Docker images and clean up unused ones with dropdown actions.',
    },
    volumes: {
        title: 'Volumes',
        subtitle: 'Visualise Docker volumes and remove them safely.',
    },
    networks: {
        title: 'Networks',
        subtitle: 'Inspect Docker networks and prune unused ones.',
    },
    settings: {
        title: 'Account settings',
        subtitle: 'Update the shared administrator username and password.',
    },
    help: {
        title: 'Quick help',
        subtitle: 'Follow the pre-made guides to complete common administration tasks.',
    },
};

document.addEventListener('DOMContentLoaded', () => {
    initialiseTheme();
    setupEventDelegates();
    setupForms();
    requestSession();
});

function initialiseTheme() {
    const storedTheme = window.localStorage.getItem('panel-theme');
    state.theme = storedTheme === 'dark' ? 'dark' : 'light';
    applyTheme();
}

function applyTheme() {
    document.body.setAttribute('data-theme', state.theme);
    const label = document.querySelector('#theme-toggle .label');
    if (label) {
        label.textContent = state.theme === 'dark' ? 'Dark' : 'Light';
    }
}

function toggleTheme() {
    state.theme = state.theme === 'dark' ? 'light' : 'dark';
    window.localStorage.setItem('panel-theme', state.theme);
    applyTheme();
}

function setupEventDelegates() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-dropdown-target]');
        if (toggle) {
            const targetId = toggle.getAttribute('data-dropdown-target');
            if (toggle.classList.contains('sidebar-item')) {
                toggle.classList.toggle('open');
                const dropdown = document.getElementById(targetId);
                if (dropdown) {
                    dropdown.classList.toggle('open');
                }
            } else {
                const dropdown = document.getElementById(targetId);
                if (dropdown) {
                    const willOpen = !dropdown.classList.contains('open');
                    closeAllDropdowns();
                    if (willOpen) {
                        dropdown.classList.add('open');
                        toggle.classList.add('active');
                    }
                }
            }
            return;
        }

        if (!event.target.closest('.dropdown-menu')) {
            closeAllDropdowns();
        }

        const viewButton = event.target.closest('[data-view]');
        if (viewButton) {
            const view = viewButton.getAttribute('data-view');
            if (view) {
                setView(view);
            }
            return;
        }

        const quickActionButton = event.target.closest('[data-quick-action]');
        if (quickActionButton) {
            const action = quickActionButton.getAttribute('data-quick-action');
            handleQuickAction(action);
            closeAllDropdowns();
            return;
        }

        const containerAction = event.target.closest('button[data-action][data-container-id]');
        if (containerAction) {
            const action = containerAction.getAttribute('data-action');
            const id = containerAction.getAttribute('data-container-id');
            const name = containerAction.getAttribute('data-container-name');
            handleContainerAction(action, id, name);
            closeAllDropdowns();
            return;
        }

        const imageAction = event.target.closest('button[data-action="remove-image"]');
        if (imageAction) {
            const id = imageAction.getAttribute('data-image-id');
            const name = imageAction.getAttribute('data-image-name');
            removeImage(id, name);
            closeAllDropdowns();
            return;
        }

        const volumeAction = event.target.closest('button[data-action="remove-volume"]');
        if (volumeAction) {
            const id = volumeAction.getAttribute('data-volume-id');
            removeVolume(id);
            closeAllDropdowns();
            return;
        }

        const networkAction = event.target.closest('button[data-action="remove-network"]');
        if (networkAction) {
            const id = networkAction.getAttribute('data-network-id');
            removeNetwork(id);
            closeAllDropdowns();
        }
    });

    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', handleLogout);
    }

    document.addEventListener('change', (event) => {
        if (event.target && event.target.id === 'console-command-select') {
            const value = event.target.value;
            const customField = document.getElementById('console-custom-field');
            if (customField) {
                customField.classList.toggle('hidden', value !== 'custom');
            }
        }
    });
}

function setupForms() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    const createForm = document.getElementById('create-container-form');
    if (createForm) {
        createForm.addEventListener('submit', handleCreateContainer);
    }

    const logsForm = document.getElementById('logs-form');
    if (logsForm) {
        logsForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(logsForm);
            const id = formData.get('id');
            const tail = formData.get('tail');
            loadLogs(id, tail);
        });
    }

    const consoleForm = document.getElementById('console-form');
    if (consoleForm) {
        consoleForm.addEventListener('submit', (event) => {
            event.preventDefault();
            runConsoleCommand();
        });
    }

    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', (event) => {
            event.preventDefault();
            updateCredentials(settingsForm, document.getElementById('settings-feedback'));
        });
    }

    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', (event) => {
            event.preventDefault();
            updateCredentials(passwordForm, document.getElementById('password-feedback'), true);
        });
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu.open').forEach((menu) => menu.classList.remove('open'));
    document.querySelectorAll('.dropdown-toggle.active').forEach((button) => button.classList.remove('active'));
}

function requestSession() {
    fetch(`${apiBase}?action=session`)
        .then((response) => response.json())
        .then((data) => {
            if (data.success === false) {
                throw new Error(data.message || 'Unable to determine session.');
            }
            state.session = {
                authenticated: Boolean(data.authenticated),
                username: data.username || 'admin',
                mustChangePassword: Boolean(data.mustChangePassword),
            };
            if (state.session.authenticated) {
                showApp();
                if (state.session.mustChangePassword) {
                    openPasswordModal();
                }
                refreshAllData();
            } else {
                showLogin();
            }
        })
        .catch(() => {
            showLogin();
        });
}

function showLogin() {
    document.getElementById('auth-view')?.classList.remove('hidden');
    document.getElementById('app')?.classList.add('hidden');
}

function showApp() {
    document.getElementById('auth-view')?.classList.add('hidden');
    document.getElementById('app')?.classList.remove('hidden');
    document.getElementById('sidebar-username').textContent = state.session.username;
    const usernameInput = document.getElementById('settings-username');
    if (usernameInput) {
        usernameInput.value = state.session.username;
    }
}

function handleLogin(event) {
    event.preventDefault();
    const form = event.target;
    const feedback = document.getElementById('login-feedback');
    feedback.textContent = 'Signing in...';
    feedback.classList.remove('error', 'success');

    const formData = new FormData(form);

    fetch(`${apiBase}?action=login`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Invalid credentials.');
            }
            state.session = {
                authenticated: true,
                username: data.username || formData.get('username') || 'admin',
                mustChangePassword: Boolean(data.mustChangePassword),
            };
            feedback.textContent = '';
            showApp();
            setView('containers');
            refreshAllData();
            if (state.session.mustChangePassword) {
                openPasswordModal();
            }
        })
        .catch((error) => {
            feedback.textContent = error.message;
            feedback.classList.add('error');
        });
}

function handleLogout() {
    fetch(`${apiBase}?action=logout`, { method: 'POST' })
        .finally(() => {
            state.session = { authenticated: false, username: 'admin', mustChangePassword: false };
            state.containers = [];
            state.images = [];
            state.volumes = [];
            state.networks = [];
            showLogin();
        });
}

function setView(view) {
    if (!VIEW_TEXT[view]) {
        view = 'containers';
    }
    closeAllDropdowns();
    state.currentView = view;
    document.querySelectorAll('.view').forEach((section) => {
        const matches = section.getAttribute('data-view') === view;
        section.classList.toggle('hidden', !matches);
    });
    const { title, subtitle } = VIEW_TEXT[view];
    const titleElement = document.getElementById('view-title');
    const subtitleElement = document.getElementById('view-subtitle');
    if (titleElement) {
        titleElement.textContent = title;
    }
    if (subtitleElement) {
        subtitleElement.textContent = subtitle;
    }

    if (view === 'containers') {
        loadContainers(true);
    }
    if (view === 'images') {
        loadImages(true);
    }
    if (view === 'volumes') {
        loadVolumes(true);
    }
    if (view === 'networks') {
        loadNetworks(true);
    }
}

function refreshAllData() {
    loadContainers();
    loadImages();
    loadVolumes();
    loadNetworks();
}

function handleQuickAction(action) {
    switch (action) {
        case 'refresh-all':
            refreshAllData();
            break;
        case 'refresh-containers':
            loadContainers(true);
            break;
        case 'refresh-images':
            loadImages(true);
            break;
        case 'refresh-volumes':
            loadVolumes(true);
            break;
        case 'refresh-networks':
            loadNetworks(true);
            break;
        default:
            break;
    }
}

function handleCreateContainer(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('create-container-message');
    messageElement.textContent = 'Deploying container...';
    messageElement.classList.remove('error', 'success');

    const formData = new FormData(form);

    fetch(`${apiBase}?action=create_container`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to create container.');
            }
            messageElement.textContent = `${data.message} (ID: ${data.containerId || 'unknown'})`;
            messageElement.classList.add('success');
            form.reset();
            loadContainers(true);
        })
        .catch((error) => {
            messageElement.textContent = error.message;
            messageElement.classList.add('error');
        });
}

function loadContainers(force = false) {
    const tableBody = document.getElementById('container-table');
    if (!force) {
        tableBody.innerHTML = '<tr><td colspan="6">Loading containers...</td></tr>';
    }
    fetch(`${apiBase}?action=list_containers`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load containers.');
            }
            state.containers = data.containers || [];
            renderContainers();
            populateContainerSelections();
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${error.message}</td></tr>`;
        });
}

function renderContainers() {
    const tableBody = document.getElementById('container-table');
    tableBody.innerHTML = '';

    if (!state.containers.length) {
        tableBody.innerHTML = '<tr><td colspan="6">No containers available.</td></tr>';
        return;
    }

    state.containers.forEach((container, index) => {
        const row = document.createElement('tr');
        const menuId = `container-action-${index}`;
        row.innerHTML = `
            <td>${escapeHtml(container.name)}</td>
            <td>${escapeHtml(container.image)}</td>
            <td>${escapeHtml(container.status)}</td>
            <td>${escapeHtml(container.ports)}</td>
            <td>${escapeHtml(container.created)}</td>
            <td>
                <div class="dropdown">
                    <button class="secondary dropdown-toggle" type="button" data-dropdown-target="${menuId}">Actions</button>
                    <div class="dropdown-menu" id="${menuId}">
                        <button type="button" data-action="start" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">Start</button>
                        <button type="button" data-action="stop" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">Stop</button>
                        <button type="button" data-action="restart" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">Restart</button>
                        <button type="button" data-action="logs" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">View logs</button>
                        <button type="button" data-action="console" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">Open console</button>
                        <button type="button" class="danger" data-action="remove" data-container-id="${encodeHtmlAttribute(container.id)}" data-container-name="${encodeHtmlAttribute(container.name)}">Remove</button>
                    </div>
                </div>
            </td>
        `;
        const labels = ['Name', 'Image', 'Status', 'Ports', 'Created', 'Actions'];
        row.querySelectorAll('td').forEach((cell, cellIndex) => {
            cell.dataset.label = labels[cellIndex] || '';
        });
        tableBody.appendChild(row);
    });
}

function populateContainerSelections() {
    const logsSelect = document.getElementById('logs-container-select');
    const consoleSelect = document.getElementById('console-container-select');
    const selects = [logsSelect, consoleSelect];
    const previousValues = selects.map((select) => select?.value || '');

    selects.forEach((select) => {
        if (!select) {
            return;
        }
        select.innerHTML = '';
        if (!state.containers.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No containers available';
            option.disabled = true;
            option.selected = true;
            select.appendChild(option);
            select.disabled = true;
        } else {
            select.disabled = false;
            state.containers.forEach((container) => {
                const option = document.createElement('option');
                option.value = container.id;
                option.textContent = container.name ? `${container.name} (${container.id})` : container.id;
                select.appendChild(option);
            });
        }
    });

    selects.forEach((select, index) => {
        if (!select || select.disabled) {
            return;
        }
        const previous = previousValues[index];
        if (previous && Array.from(select.options).some((opt) => opt.value === previous)) {
            select.value = previous;
        }
    });
}

function handleContainerAction(action, id, name) {
    if (!id) {
        return;
    }

    if (action === 'logs') {
        setView('logs');
        const logsSelect = document.getElementById('logs-container-select');
        if (logsSelect && !logsSelect.disabled) {
            logsSelect.value = id;
            loadLogs(id, document.getElementById('logs-tail-select')?.value || 100);
        }
        return;
    }

    if (action === 'console') {
        setView('console');
        const consoleSelect = document.getElementById('console-container-select');
        if (consoleSelect && !consoleSelect.disabled) {
            consoleSelect.value = id;
        }
        document.getElementById('console-output').textContent = `Ready to run commands inside ${name || id}.`;
        return;
    }

    if (action === 'remove') {
        const confirmed = window.confirm(`Remove container ${name || id}? This stops it if running.`);
        if (!confirmed) {
            return;
        }
    }

    const routes = {
        start: 'start_container',
        stop: 'stop_container',
        restart: 'restart_container',
        remove: 'remove_container',
    };

    const apiAction = routes[action];
    if (!apiAction) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    fetch(`${apiBase}?action=${apiAction}`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Command failed');
            }
            loadContainers(true);
        })
        .catch((error) => {
            window.alert(error.message);
        });
}

function loadImages(force = false) {
    const tableBody = document.getElementById('image-table');
    if (!force) {
        tableBody.innerHTML = '<tr><td colspan="6">Loading images...</td></tr>';
    }
    fetch(`${apiBase}?action=list_images`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load images.');
            }
            state.images = data.images || [];
            renderImages();
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${error.message}</td></tr>`;
        });
}

function renderImages() {
    const tableBody = document.getElementById('image-table');
    tableBody.innerHTML = '';

    if (!state.images.length) {
        tableBody.innerHTML = '<tr><td colspan="6">No images found.</td></tr>';
        return;
    }

    state.images.forEach((image, index) => {
        const row = document.createElement('tr');
        const menuId = `image-action-${index}`;
        row.innerHTML = `
            <td>${escapeHtml(image.repository)}</td>
            <td>${escapeHtml(image.tag)}</td>
            <td>${escapeHtml(image.id)}</td>
            <td>${escapeHtml(image.size)}</td>
            <td>${escapeHtml(image.created)}</td>
            <td>
                <div class="dropdown">
                    <button class="secondary dropdown-toggle" type="button" data-dropdown-target="${menuId}">Actions</button>
                    <div class="dropdown-menu" id="${menuId}">
                        <button type="button" data-action="remove-image" data-image-id="${encodeHtmlAttribute(image.id)}" data-image-name="${encodeHtmlAttribute(image.repository)}">Remove image</button>
                    </div>
                </div>
            </td>
        `;
        const labels = ['Repository', 'Tag', 'Image ID', 'Size', 'Created', 'Actions'];
        row.querySelectorAll('td').forEach((cell, cellIndex) => {
            cell.dataset.label = labels[cellIndex] || '';
        });
        tableBody.appendChild(row);
    });
}

function removeImage(id, name) {
    if (!id) {
        return;
    }
    const confirmed = window.confirm(`Remove image ${name || id}?`);
    if (!confirmed) {
        return;
    }
    const formData = new FormData();
    formData.append('id', id);
    fetch(`${apiBase}?action=remove_image`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to remove image.');
            }
            loadImages(true);
        })
        .catch((error) => window.alert(error.message));
}

function loadVolumes(force = false) {
    const tableBody = document.getElementById('volume-table');
    if (!force) {
        tableBody.innerHTML = '<tr><td colspan="4">Loading volumes...</td></tr>';
    }
    fetch(`${apiBase}?action=list_volumes`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load volumes.');
            }
            state.volumes = data.volumes || [];
            renderVolumes();
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${error.message}</td></tr>`;
        });
}

function renderVolumes() {
    const tableBody = document.getElementById('volume-table');
    tableBody.innerHTML = '';

    if (!state.volumes.length) {
        tableBody.innerHTML = '<tr><td colspan="4">No volumes found.</td></tr>';
        return;
    }

    state.volumes.forEach((volume, index) => {
        const row = document.createElement('tr');
        const menuId = `volume-action-${index}`;
        row.innerHTML = `
            <td>${escapeHtml(volume.name)}</td>
            <td>${escapeHtml(volume.driver)}</td>
            <td>${escapeHtml(volume.mountpoint)}</td>
            <td>
                <div class="dropdown">
                    <button class="secondary dropdown-toggle" type="button" data-dropdown-target="${menuId}">Actions</button>
                    <div class="dropdown-menu" id="${menuId}">
                        <button type="button" data-action="remove-volume" data-volume-id="${encodeHtmlAttribute(volume.name)}">Remove volume</button>
                    </div>
                </div>
            </td>
        `;
        const labels = ['Name', 'Driver', 'Mountpoint', 'Actions'];
        row.querySelectorAll('td').forEach((cell, cellIndex) => {
            cell.dataset.label = labels[cellIndex] || '';
        });
        tableBody.appendChild(row);
    });
}

function removeVolume(id) {
    if (!id) {
        return;
    }
    const confirmed = window.confirm(`Remove volume ${id}?`);
    if (!confirmed) {
        return;
    }
    const formData = new FormData();
    formData.append('id', id);
    fetch(`${apiBase}?action=remove_volume`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to remove volume.');
            }
            loadVolumes(true);
        })
        .catch((error) => window.alert(error.message));
}

function loadNetworks(force = false) {
    const tableBody = document.getElementById('network-table');
    if (!force) {
        tableBody.innerHTML = '<tr><td colspan="5">Loading networks...</td></tr>';
    }
    fetch(`${apiBase}?action=list_networks`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load networks.');
            }
            state.networks = data.networks || [];
            renderNetworks();
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="5" class="error">${error.message}</td></tr>`;
        });
}

function renderNetworks() {
    const tableBody = document.getElementById('network-table');
    tableBody.innerHTML = '';

    if (!state.networks.length) {
        tableBody.innerHTML = '<tr><td colspan="5">No networks found.</td></tr>';
        return;
    }

    state.networks.forEach((network, index) => {
        const row = document.createElement('tr');
        const menuId = `network-action-${index}`;
        row.innerHTML = `
            <td>${escapeHtml(network.name)}</td>
            <td>${escapeHtml(network.id)}</td>
            <td>${escapeHtml(network.driver)}</td>
            <td>${escapeHtml(network.scope)}</td>
            <td>
                <div class="dropdown">
                    <button class="secondary dropdown-toggle" type="button" data-dropdown-target="${menuId}">Actions</button>
                    <div class="dropdown-menu" id="${menuId}">
                        <button type="button" data-action="remove-network" data-network-id="${encodeHtmlAttribute(network.id)}">Remove network</button>
                    </div>
                </div>
            </td>
        `;
        const labels = ['Name', 'ID', 'Driver', 'Scope', 'Actions'];
        row.querySelectorAll('td').forEach((cell, cellIndex) => {
            cell.dataset.label = labels[cellIndex] || '';
        });
        tableBody.appendChild(row);
    });
}

function removeNetwork(id) {
    if (!id) {
        return;
    }
    const confirmed = window.confirm(`Remove network ${id}?`);
    if (!confirmed) {
        return;
    }
    const formData = new FormData();
    formData.append('id', id);
    fetch(`${apiBase}?action=remove_network`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to remove network.');
            }
            loadNetworks(true);
        })
        .catch((error) => window.alert(error.message));
}

function loadLogs(id, tail = 100) {
    if (!id) {
        document.getElementById('log-output').textContent = 'Select a container to view logs.';
        return;
    }
    const output = document.getElementById('log-output');
    output.textContent = 'Fetching logs...';
    fetch(`${apiBase}?action=container_logs&id=${encodeURIComponent(id)}&tail=${encodeURIComponent(tail)}`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch logs.');
            }
            output.textContent = data.logs || 'No logs available.';
        })
        .catch((error) => {
            output.textContent = error.message;
        });
}

function runConsoleCommand() {
    const containerSelect = document.getElementById('console-container-select');
    const presetSelect = document.getElementById('console-command-select');
    const customInput = document.getElementById('console-custom-input');
    const output = document.getElementById('console-output');

    if (!containerSelect || containerSelect.disabled) {
        output.textContent = 'No container selected.';
        return;
    }

    const id = containerSelect.value;
    let command = '';
    switch (presetSelect.value) {
        case 'bash':
            command = 'bash -lc "whoami && pwd"';
            break;
        case 'ls':
            command = 'ls -al';
            break;
        case 'ps':
            command = 'ps aux';
            break;
        case 'df':
            command = 'df -h';
            break;
        case 'custom':
            command = customInput.value.trim();
            break;
        default:
            command = 'bash';
            break;
    }

    if (!command) {
        output.textContent = 'Enter a command to run.';
        return;
    }

    output.textContent = 'Executing command...';
    const formData = new FormData();
    formData.append('id', id);
    formData.append('command', command);

    fetch(`${apiBase}?action=container_exec`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to run command.');
            }
            output.textContent = data.output || 'Command executed with no output.';
        })
        .catch((error) => {
            output.textContent = error.message;
        });
}

function openPasswordModal() {
    document.getElementById('password-modal')?.classList.remove('hidden');
}

function closePasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.reset();
    }
    const feedback = document.getElementById('password-feedback');
    if (feedback) {
        feedback.textContent = '';
    }
}

function updateCredentials(form, feedbackElement, forceNewPassword = false) {
    const formData = new FormData(form);
    if (!formData.get('username')) {
        formData.set('username', state.session.username);
    }

    const newPassword = (formData.get('newPassword') || '').toString();
    const confirmPassword = (formData.get('confirmPassword') || '').toString();

    if (forceNewPassword || state.session.mustChangePassword) {
        if (!newPassword) {
            feedbackElement.textContent = 'New password is required.';
            feedbackElement.classList.add('error');
            return;
        }
        if (newPassword !== confirmPassword) {
            feedbackElement.textContent = 'Passwords must match.';
            feedbackElement.classList.add('error');
            return;
        }
    } else if (newPassword && newPassword !== confirmPassword) {
        feedbackElement.textContent = 'Passwords must match.';
        feedbackElement.classList.add('error');
        return;
    }

    feedbackElement.textContent = 'Saving changes...';
    feedbackElement.classList.remove('error', 'success');

    fetch(`${apiBase}?action=update_credentials`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to update credentials.');
            }
            feedbackElement.textContent = data.message || 'Credentials updated.';
            feedbackElement.classList.add('success');
            state.session.username = data.username || formData.get('username') || state.session.username;
            state.session.mustChangePassword = Boolean(data.mustChangePassword);
            document.getElementById('sidebar-username').textContent = state.session.username;
            const settingsUsername = document.getElementById('settings-username');
            if (settingsUsername) {
                settingsUsername.value = state.session.username;
            }
            if (form.id === 'password-form') {
                closePasswordModal();
            } else {
                form.reset();
                if (settingsUsername) {
                    settingsUsername.value = state.session.username;
                }
            }
        })
        .catch((error) => {
            feedbackElement.textContent = error.message;
            feedbackElement.classList.add('error');
        });
}

function escapeHtml(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function encodeHtmlAttribute(value = '') {
    return escapeHtml(value).replace(/"/g, '&quot;');
}
