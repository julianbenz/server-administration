<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Administration Dashboard</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body data-theme="light">
    <div id="auth-view" class="auth-view">
        <form id="login-form" class="card">
            <h1 class="card-title">Server Administration Panel</h1>
            <p class="card-subtitle">Sign in to manage Docker without command-line knowledge.</p>
            <label class="field">
                <span>Username</span>
                <input type="text" name="username" placeholder="admin" autocomplete="username" required>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </label>
            <button type="submit" class="primary">Sign in</button>
            <p class="form-feedback" id="login-feedback" role="alert"></p>
        </form>
    </div>

    <div id="app" class="app hidden">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand">Server Admin</div>
                <button class="icon-button" id="theme-toggle" type="button" aria-label="Toggle theme">
                    <span class="icon">🌓</span>
                    <span class="label">Light</span>
                </button>
            </div>

            <div class="sidebar-body">
                <div class="sidebar-section">
                    <button class="sidebar-item" type="button" data-dropdown-target="nav-containers">Containers</button>
                    <div class="sidebar-dropdown" id="nav-containers">
                        <button class="menu-item" type="button" data-view="containers">Overview</button>
                        <button class="menu-item" type="button" data-view="deploy">Deploy Container</button>
                        <button class="menu-item" type="button" data-view="logs">Container Logs</button>
                        <button class="menu-item" type="button" data-view="console">Interactive Console</button>
                    </div>
                </div>
                <div class="sidebar-section">
                    <button class="sidebar-item" type="button" data-dropdown-target="nav-resources">Resources</button>
                    <div class="sidebar-dropdown" id="nav-resources">
                        <button class="menu-item" type="button" data-view="images">Images</button>
                        <button class="menu-item" type="button" data-view="volumes">Volumes</button>
                        <button class="menu-item" type="button" data-view="networks">Networks</button>
                    </div>
                </div>
                <div class="sidebar-section">
                    <button class="sidebar-item" type="button" data-dropdown-target="nav-support">Support</button>
                    <div class="sidebar-dropdown" id="nav-support">
                        <button class="menu-item" type="button" data-view="help">Quick Help</button>
                    </div>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar" aria-hidden="true">👤</div>
                    <div>
                        <p class="user-name" id="sidebar-username">admin</p>
                        <p class="user-role">Panel Administrator</p>
                    </div>
                </div>
                <button class="secondary" type="button" data-view="settings">Settings</button>
                <button class="secondary danger" type="button" id="logout-button">Sign out</button>
            </div>
        </aside>

        <main class="content">
            <header class="content-header">
                <div>
                    <h2 id="view-title">Containers</h2>
                    <p id="view-subtitle" class="subtitle">Manage running Docker workloads with guided controls.</p>
                </div>
                <div class="content-actions">
                    <div class="dropdown">
                        <button class="secondary dropdown-toggle" type="button" data-dropdown-target="quick-actions-menu">Quick Actions</button>
                        <div class="dropdown-menu" id="quick-actions-menu">
                            <button type="button" data-quick-action="refresh-all">Refresh everything</button>
                            <button type="button" data-quick-action="refresh-containers">Refresh containers</button>
                            <button type="button" data-quick-action="refresh-images">Refresh images</button>
                            <button type="button" data-quick-action="refresh-volumes">Refresh volumes</button>
                            <button type="button" data-quick-action="refresh-networks">Refresh networks</button>
                        </div>
                    </div>
                    <button class="secondary" type="button" data-view="settings">Account</button>
                </div>
            </header>

            <section class="view" data-view="containers">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Container overview</h3>
                        <div class="dropdown">
                            <button class="secondary dropdown-toggle" type="button" data-dropdown-target="container-actions">Container options</button>
                            <div class="dropdown-menu" id="container-actions">
                                <button type="button" data-quick-action="refresh-containers">Refresh list</button>
                                <button type="button" data-view="deploy">Deploy new container</button>
                                <button type="button" data-view="logs">View logs</button>
                                <button type="button" data-view="console">Open console</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
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
                            <tbody id="container-table"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="view hidden" data-view="deploy">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Deploy container</h3>
                        <p class="panel-description">Select an image, optional ports, volumes, and click deploy. No Docker CLI required.</p>
                    </div>
                    <form id="create-container-form" class="grid-form">
                        <label class="field">
                            <span>Container name</span>
                            <input type="text" name="name" placeholder="my-service" required>
                        </label>
                        <label class="field">
                            <span>Image</span>
                            <input type="text" name="image" placeholder="nginx:latest" required>
                        </label>
                        <label class="field">
                            <span>Optional command</span>
                            <input type="text" name="command" placeholder="e.g. npm start">
                        </label>
                        <label class="field">
                            <span>Port mapping</span>
                            <input type="text" name="ports" placeholder="8080:80, 443:443">
                            <small>Use host:container pairs separated by commas.</small>
                        </label>
                        <label class="field">
                            <span>Environment variables</span>
                            <input type="text" name="env" placeholder="KEY=value, MODE=prod">
                        </label>
                        <label class="field">
                            <span>Volume mounts</span>
                            <input type="text" name="volumes" placeholder="/host:/container, data:/data">
                        </label>
                        <button class="primary" type="submit">Deploy</button>
                        <p class="form-feedback" id="create-container-message" role="alert"></p>
                    </form>
                </div>
            </section>

            <section class="view hidden" data-view="logs">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Container logs</h3>
                        <p class="panel-description">Choose a container and how many lines to tail. Logs appear instantly.</p>
                    </div>
                    <form id="logs-form" class="inline-form">
                        <label class="field">
                            <span>Container</span>
                            <select id="logs-container-select" name="id"></select>
                        </label>
                        <label class="field">
                            <span>Tail lines</span>
                            <select id="logs-tail-select" name="tail">
                                <option value="50">50 lines</option>
                                <option value="100" selected>100 lines</option>
                                <option value="200">200 lines</option>
                                <option value="500">500 lines</option>
                            </select>
                        </label>
                        <button class="primary" type="submit">Fetch logs</button>
                    </form>
                    <pre class="log-output" id="log-output">Select a container to view logs.</pre>
                </div>
            </section>

            <section class="view hidden" data-view="console">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Interactive console</h3>
                        <p class="panel-description">Launch curated console commands or inspect the container with a guided workflow.</p>
                    </div>
                    <form id="console-form" class="grid-form">
                        <label class="field">
                            <span>Container</span>
                            <select id="console-container-select" name="id"></select>
                        </label>
                        <label class="field">
                            <span>Command</span>
                            <select id="console-command-select" name="commandPreset">
                                <option value="bash">Shell info (whoami &amp;&amp; pwd)</option>
                                <option value="ls">List files (ls -al)</option>
                                <option value="ps">List processes (ps aux)</option>
                                <option value="df">Disk usage (df -h)</option>
                                <option value="custom">Custom command</option>
                            </select>
                        </label>
                        <label class="field hidden" id="console-custom-field">
                            <span>Custom command</span>
                            <input type="text" id="console-custom-input" placeholder="Enter a Docker exec command">
                        </label>
                        <button class="primary" type="submit">Run command</button>
                    </form>
                    <pre class="log-output" id="console-output">Choose a container and command to view the console output.</pre>
                </div>
            </section>

              <section class="view hidden" data-view="images">
                  <div class="panel">
                      <div class="panel-header">
                          <h3>Images</h3>
                          <div class="dropdown">
                              <button class="secondary dropdown-toggle" type="button" data-dropdown-target="images-actions">Image options</button>
                              <div class="dropdown-menu" id="images-actions">
                                  <button type="button" data-quick-action="refresh-images">Refresh list</button>
                              </div>
                          </div>
                      </div>
                      <form id="pull-image-form" class="inline-form">
                          <label class="field">
                              <span>Image reference</span>
                              <input type="text" name="image" placeholder="nginx:latest" required>
                              <small>Enter the repository tag, e.g. <code>library/nginx:latest</code>.</small>
                          </label>
                          <button class="primary" type="submit">Pull image</button>
                          <p class="form-feedback" id="pull-image-feedback" role="alert"></p>
                      </form>
                      <div class="table-wrapper">
                          <table>
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
                            <tbody id="image-table"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="view hidden" data-view="volumes">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Volumes</h3>
                        <div class="dropdown">
                            <button class="secondary dropdown-toggle" type="button" data-dropdown-target="volumes-actions">Volume options</button>
                            <div class="dropdown-menu" id="volumes-actions">
                                <button type="button" data-quick-action="refresh-volumes">Refresh list</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Driver</th>
                                    <th>Mountpoint</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="volume-table"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="view hidden" data-view="networks">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Networks</h3>
                        <div class="dropdown">
                            <button class="secondary dropdown-toggle" type="button" data-dropdown-target="networks-actions">Network options</button>
                            <div class="dropdown-menu" id="networks-actions">
                                <button type="button" data-quick-action="refresh-networks">Refresh list</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>ID</th>
                                    <th>Driver</th>
                                    <th>Scope</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="network-table"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="view hidden" data-view="settings">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Account settings</h3>
                        <p class="panel-description">Update the shared administrator credentials used to access this panel.</p>
                    </div>
                    <form id="settings-form" class="grid-form">
                        <label class="field">
                            <span>Current username</span>
                            <input type="text" id="settings-username" name="username" required>
                        </label>
                        <label class="field">
                            <span>Current password</span>
                            <input type="password" name="currentPassword" autocomplete="current-password" required>
                        </label>
                        <label class="field">
                            <span>New password</span>
                            <input type="password" name="newPassword" autocomplete="new-password" placeholder="Leave blank to keep current password">
                        </label>
                        <label class="field">
                            <span>Confirm new password</span>
                            <input type="password" name="confirmPassword" autocomplete="new-password">
                        </label>
                        <button class="primary" type="submit">Save changes</button>
                        <p class="form-feedback" id="settings-feedback" role="alert"></p>
                    </form>
                </div>
            </section>

            <section class="view hidden" data-view="help">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Quick help</h3>
                        <p class="panel-description">Follow these guided dropdown menus to complete frequent tasks.</p>
                    </div>
                    <div class="help-grid">
                        <article class="help-card">
                            <h4>Deploy a service</h4>
                            <ol>
                                <li>Select <strong>Containers → Deploy container</strong>.</li>
                                <li>Choose an image and optional extras.</li>
                                <li>Click <strong>Deploy</strong>. Your container starts automatically.</li>
                            </ol>
                        </article>
                        <article class="help-card">
                            <h4>Inspect logs</h4>
                            <ol>
                                <li>Open <strong>Containers → Container logs</strong>.</li>
                                <li>Pick the container and number of lines.</li>
                                <li>Press <strong>Fetch logs</strong> to review output.</li>
                            </ol>
                        </article>
                        <article class="help-card">
                            <h4>Access a console</h4>
                            <ol>
                                <li>Open <strong>Containers → Interactive console</strong>.</li>
                                <li>Choose the desired container.</li>
                                <li>Select a prepared command or use a custom entry.</li>
                            </ol>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal hidden" id="password-modal" role="dialog" aria-modal="true">
        <div class="card">
            <h2 class="card-title">Secure your panel</h2>
            <p class="card-subtitle">Change the default administrator password before continuing.</p>
            <form id="password-form" class="grid-form">
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="currentPassword" autocomplete="current-password" required>
                </label>
                <label class="field">
                    <span>New password</span>
                    <input type="password" name="newPassword" autocomplete="new-password" required>
                </label>
                <label class="field">
                    <span>Confirm new password</span>
                    <input type="password" name="confirmPassword" autocomplete="new-password" required>
                </label>
                <button class="primary" type="submit">Update password</button>
                <p class="form-feedback" id="password-feedback" role="alert"></p>
            </form>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
