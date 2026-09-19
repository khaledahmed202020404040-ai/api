FROM php:8.2-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libpq-dev \
	&& docker-php-ext-install pdo_pgsql \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

RUN printf '%s\n' \
	'<?php' \
	'header("Access-Control-Allow-Origin: *");' \
	'header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");' \
	'header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");' \
	'if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }' \
	'$uri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";' \
	'$normalized = "/" . trim((string) preg_replace("#/+#", "/", $uri), "/");' \
	'$relativePath = str_starts_with($normalized, "/api/") ? substr($normalized, 4) : $normalized;' \
	'$relativePath = "/" . ltrim($relativePath, "/");' \
	'$candidate = __DIR__ . $relativePath;' \
	'if ($relativePath === "/") { $candidate = __DIR__ . "/android/index.php"; }' \
	'elseif (str_ends_with($relativePath, "/")) { $candidate = rtrim($candidate, "/") . "/index.php"; }' \
	'elseif (!str_ends_with($relativePath, ".php") && is_dir($candidate)) { $candidate .= "/index.php"; }' \
	'if (is_file($candidate)) { require $candidate; return; }' \
	'header("Content-Type: application/json; charset=utf-8"); http_response_code(404);' \
	'echo json_encode(["status" => "error", "success" => false, "message" => "Route not found", "path" => $normalized], JSON_UNESCAPED_SLASHES);' \
	> /app/router.php

CMD ["sh", "-c", "if [ -f /app/router.php ]; then ROOT=/app; elif [ -f /app/api/router.php ]; then ROOT=/app/api; else echo 'router.php not found'; exit 1; fi; php -S 0.0.0.0:${PORT:-8080} -t \"$ROOT\" \"$ROOT/router.php\""]
