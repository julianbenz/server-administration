<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Administration Dashboard</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <header>
        <h1>Server Administration Dashboard</h1>
        <p class="subtitle">Lightweight Docker management inspired by Portainer</p>
    </header>

    <main>
        <section class="panel" id="container-panel">
            <div class="panel-header">
                <h2>Containers</h2>
                <button class="refresh" data-target="containers">Refresh</button>
            </div>
            <div class="table-wrapper">
                <table id="container-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Ports</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="create-container-panel">
            <div class="panel-header">
                <h2>Create Container</h2>
            </div>
            <form id="create-container-form">
                <div class="grid">
                    <label>
                        Container Name
                        <input type="text" name="name" placeholder="e.g. my-web" required>
                    </label>
                    <label>
                        Image
                        <input type="text" name="image" placeholder="nginx:latest" required>
                    </label>
                    <label>
                        Command (optional)
                        <input type="text" name="command" placeholder="Command to run">
                    </label>
                    <label>
                        Port Mapping (host:container)
                        <input type="text" name="ports" placeholder="8080:80">
                    </label>
                    <label>
                        Environment Variables (KEY=VALUE, comma separated)
                        <input type="text" name="env" placeholder="MODE=prod,DEBUG=false">
                    </label>
                    <label>
                        Volume Mounts (host:container, comma separated)
                        <input type="text" name="volumes" placeholder="/host/path:/container/path">
                    </label>
                </div>
                <button type="submit">Deploy Container</button>
                <p class="form-message" id="create-container-message"></p>
            </form>
        </section>

        <section class="panel" id="image-panel">
            <div class="panel-header">
                <h2>Images</h2>
                <button class="refresh" data-target="images">Refresh</button>
            </div>
            <div class="table-wrapper">
                <table id="image-table">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Tag</th>
                            <th>Image ID</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="volume-panel">
            <div class="panel-header">
                <h2>Volumes</h2>
                <button class="refresh" data-target="volumes">Refresh</button>
            </div>
            <div class="table-wrapper">
                <table id="volume-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Driver</th>
                            <th>Mountpoint</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="network-panel">
            <div class="panel-header">
                <h2>Networks</h2>
                <button class="refresh" data-target="networks">Refresh</button>
            </div>
            <div class="table-wrapper">
                <table id="network-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Driver</th>
                            <th>Scope</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="logs-panel">
            <div class="panel-header">
                <h2>Container Logs</h2>
            </div>
            <form id="log-form">
                <div class="form-inline">
                    <label>
                        Container Name or ID
                        <input type="text" name="id" placeholder="Container ID" required>
                    </label>
                    <label>
                        Tail Lines
                        <input type="number" name="tail" value="100" min="1" max="1000">
                    </label>
                    <button type="submit">Fetch Logs</button>
                </div>
            </form>
            <pre id="log-output" class="log-output">Select a container to view recent logs.</pre>
        </section>
    </main>

    <template id="action-buttons-template">
        <div class="actions">
            <button data-action="start">Start</button>
            <button data-action="stop">Stop</button>
            <button data-action="restart">Restart</button>
            <button data-action="remove" class="danger">Remove</button>
            <button data-action="logs">Logs</button>
        </div>
    </template>

    <script src="assets/app.js"></script>
</body>
</html>
