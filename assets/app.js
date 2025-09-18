const apiBase = 'api.php';

document.addEventListener('DOMContentLoaded', () => {
    loadContainers();
    loadImages();
    loadVolumes();
    loadNetworks();

    document.querySelectorAll('button.refresh').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.target;
            switch (target) {
                case 'containers':
                    loadContainers(true);
                    break;
                case 'images':
                    loadImages(true);
                    break;
                case 'volumes':
                    loadVolumes(true);
                    break;
                case 'networks':
                    loadNetworks(true);
                    break;
            }
        });
    });

    document.getElementById('create-container-form').addEventListener('submit', handleCreateContainer);
    document.getElementById('log-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        loadLogs(formData.get('id'), formData.get('tail'));
    });
});

function handleCreateContainer(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('create-container-message');
    messageElement.textContent = 'Deploying container...';
    messageElement.className = 'form-message active';

    const formData = new FormData(form);

    fetch(`${apiBase}?action=create_container`, {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to create container');
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

function loadContainers(refresh = false) {
    const tableBody = document.querySelector('#container-table tbody');
    if (!refresh) {
        tableBody.innerHTML = '<tr><td colspan="6">Loading containers...</td></tr>';
    }

    fetch(`${apiBase}?action=list_containers`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch containers');
            }
            renderContainers(data.containers || []);
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${error.message}</td></tr>`;
        });
}

function renderContainers(containers) {
    const tableBody = document.querySelector('#container-table tbody');
    tableBody.innerHTML = '';

    if (!containers.length) {
        tableBody.innerHTML = '<tr><td colspan="6">No containers available.</td></tr>';
        return;
    }

    containers.forEach((container) => {
        const row = document.createElement('tr');
        row.dataset.id = container.id;
        row.innerHTML = `
            <td>${escapeHtml(container.name)}</td>
            <td>${escapeHtml(container.image)}</td>
            <td>${escapeHtml(container.status)}</td>
            <td>${escapeHtml(container.ports)}</td>
            <td>${escapeHtml(container.created)}</td>
            <td></td>
        `;

        const labels = ['Name', 'Image', 'Status', 'Ports', 'Created', 'Actions'];
        row.querySelectorAll('td').forEach((cell, index) => {
            cell.setAttribute('data-label', labels[index] || '');
        });

        const actionsTemplate = document.getElementById('action-buttons-template');
        const actions = actionsTemplate.content.cloneNode(true);
        actions.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => handleContainerAction(button.dataset.action, container.id, container.name));
        });
        row.querySelector('td:last-child').appendChild(actions);
        tableBody.appendChild(row);
    });
}

function handleContainerAction(action, id, name) {
    if (action === 'remove') {
        const confirmed = window.confirm(`Remove container ${name || id}?`);
        if (!confirmed) {
            return;
        }
    }

    if (action === 'logs') {
        document.querySelector('#log-form input[name="id"]').value = name || id;
        loadLogs(name || id);
        return;
    }

    const messages = {
        start: 'start_container',
        stop: 'stop_container',
        restart: 'restart_container',
        remove: 'remove_container',
    };

    const apiAction = messages[action];
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
            alert(error.message);
        });
}

function loadImages(refresh = false) {
    const tableBody = document.querySelector('#image-table tbody');
    if (!refresh) {
        tableBody.innerHTML = '<tr><td colspan="6">Loading images...</td></tr>';
    }

    fetch(`${apiBase}?action=list_images`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch images');
            }
            renderImages(data.images || []);
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${error.message}</td></tr>`;
        });
}

function renderImages(images) {
    const tableBody = document.querySelector('#image-table tbody');
    tableBody.innerHTML = '';

    if (!images.length) {
        tableBody.innerHTML = '<tr><td colspan="6">No images found.</td></tr>';
        return;
    }

    images.forEach((image) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(image.repository)}</td>
            <td>${escapeHtml(image.tag)}</td>
            <td>${escapeHtml(image.id)}</td>
            <td>${escapeHtml(image.size)}</td>
            <td>${escapeHtml(image.created)}</td>
            <td><button class="danger" data-image="${encodeURIComponent(image.id)}">Remove</button></td>
        `;

        const labels = ['Repository', 'Tag', 'Image ID', 'Size', 'Created', 'Actions'];
        row.querySelectorAll('td').forEach((cell, index) => {
            cell.setAttribute('data-label', labels[index] || '');
        });

        row.querySelector('button').addEventListener('click', () => {
            const confirmed = window.confirm(`Remove image ${image.repository}:${image.tag}?`);
            if (!confirmed) {
                return;
            }
            const formData = new FormData();
            formData.append('id', image.id);
            fetch(`${apiBase}?action=remove_image`, {
                method: 'POST',
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to remove image');
                    }
                    loadImages(true);
                })
                .catch((error) => alert(error.message));
        });

        tableBody.appendChild(row);
    });
}

function loadVolumes(refresh = false) {
    const tableBody = document.querySelector('#volume-table tbody');
    if (!refresh) {
        tableBody.innerHTML = '<tr><td colspan="4">Loading volumes...</td></tr>';
    }

    fetch(`${apiBase}?action=list_volumes`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch volumes');
            }
            renderVolumes(data.volumes || []);
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${error.message}</td></tr>`;
        });
}

function renderVolumes(volumes) {
    const tableBody = document.querySelector('#volume-table tbody');
    tableBody.innerHTML = '';

    if (!volumes.length) {
        tableBody.innerHTML = '<tr><td colspan="4">No volumes found.</td></tr>';
        return;
    }

    volumes.forEach((volume) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(volume.name)}</td>
            <td>${escapeHtml(volume.driver)}</td>
            <td>${escapeHtml(volume.mountpoint)}</td>
            <td><button class="danger">Remove</button></td>
        `;

        const labels = ['Name', 'Driver', 'Mountpoint', 'Actions'];
        row.querySelectorAll('td').forEach((cell, index) => {
            cell.setAttribute('data-label', labels[index] || '');
        });

        row.querySelector('button').addEventListener('click', () => {
            const confirmed = window.confirm(`Remove volume ${volume.name}?`);
            if (!confirmed) {
                return;
            }
            const formData = new FormData();
            formData.append('id', volume.name);
            fetch(`${apiBase}?action=remove_volume`, {
                method: 'POST',
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to remove volume');
                    }
                    loadVolumes(true);
                })
                .catch((error) => alert(error.message));
        });

        tableBody.appendChild(row);
    });
}

function loadNetworks(refresh = false) {
    const tableBody = document.querySelector('#network-table tbody');
    if (!refresh) {
        tableBody.innerHTML = '<tr><td colspan="5">Loading networks...</td></tr>';
    }

    fetch(`${apiBase}?action=list_networks`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch networks');
            }
            renderNetworks(data.networks || []);
        })
        .catch((error) => {
            tableBody.innerHTML = `<tr><td colspan="5" class="error">${error.message}</td></tr>`;
        });
}

function renderNetworks(networks) {
    const tableBody = document.querySelector('#network-table tbody');
    tableBody.innerHTML = '';

    if (!networks.length) {
        tableBody.innerHTML = '<tr><td colspan="5">No networks found.</td></tr>';
        return;
    }

    networks.forEach((network) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(network.name)}</td>
            <td>${escapeHtml(network.id)}</td>
            <td>${escapeHtml(network.driver)}</td>
            <td>${escapeHtml(network.scope)}</td>
            <td><button class="danger">Remove</button></td>
        `;

        const labels = ['Name', 'ID', 'Driver', 'Scope', 'Actions'];
        row.querySelectorAll('td').forEach((cell, index) => {
            cell.setAttribute('data-label', labels[index] || '');
        });

        row.querySelector('button').addEventListener('click', () => {
            const confirmed = window.confirm(`Remove network ${network.name}?`);
            if (!confirmed) {
                return;
            }
            const formData = new FormData();
            formData.append('id', network.id);
            fetch(`${apiBase}?action=remove_network`, {
                method: 'POST',
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to remove network');
                    }
                    loadNetworks(true);
                })
                .catch((error) => alert(error.message));
        });

        tableBody.appendChild(row);
    });
}

function loadLogs(id, tail = 100) {
    if (!id) {
        return;
    }
    const output = document.getElementById('log-output');
    output.textContent = 'Fetching logs...';

    fetch(`${apiBase}?action=container_logs&id=${encodeURIComponent(id)}&tail=${encodeURIComponent(tail)}`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to fetch logs');
            }
            output.textContent = data.logs || 'No logs available.';
        })
        .catch((error) => {
            output.textContent = error.message;
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
