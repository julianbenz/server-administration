# Server Administration Tool

A lightweight, single-host Docker management dashboard inspired by Portainer. The tool is built with PHP, HTML, CSS and vanilla JavaScript and provides a clean interface to inspect and manage containers, images, volumes and networks directly from the browser.

## Features

- Overview of every Docker container on the host with controls to start, stop, restart, remove or inspect logs.
- Container deployment form supporting common runtime options (ports, environment variables, volumes and custom commands).
- Docker image catalogue with quick removal actions.
- Volume and network listings with safe deletion helpers.
- Live log viewer for any container with configurable tail length.
- Modern responsive UI optimised for both desktop and mobile devices.

> **Security note:** The dashboard shells out to the Docker CLI. Deploy it only on trusted networks, restrict access behind authentication (e.g. reverse proxy or VPN) and run it on hosts where you control who can reach the site.

## Requirements

- Ubuntu/Debian based server with `apt` available.
- Root (or sudo) access to install system packages and configure Apache.
- Docker Engine 20.10+ and Apache HTTP server with PHP 8.1+.

The provided `install.sh` script will install and configure the required dependencies automatically.

## Quick installation

1. Clone this repository on the target server.
2. From the repository root run:

   ```bash
   sudo bash install.sh
   ```

   The installer will:

   - Install Apache, PHP, Docker Engine and supporting utilities.
   - Enable and start the Docker and Apache services.
   - Deploy the application to `/var/www/server-admin`.
   - Configure an Apache virtual host named `server-admin`.

3. Browse to `http://<server-ip>/` to access the dashboard.

## Manual deployment

If you prefer to deploy manually:

1. Install Docker and a PHP-capable web server.
2. Copy the repository files to your document root.
3. Ensure the web server user has permission to communicate with Docker (e.g. by adding the user to the `docker` group or exposing the Docker socket securely).
4. Point your browser to the application URL.

## Development

- The front-end assets live under `assets/` and the PHP API resides in `api.php`.
- No framework dependencies are required; any PHP-enabled web server can host the tool.
- Feel free to extend the API to cover additional Docker commands your environment requires.

## License

This project is provided as-is without warranty. Evaluate security implications before running it in production.
