<?php

namespace Core;

class Router
{
    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = parse_url(BASE_URL, PHP_URL_PATH);

        if ($base !== '' && $base !== '/' && ($uri === $base || str_starts_with($uri, $base . '/'))) {
            $uri = substr($uri, strlen($base));
        }
        $uri = trim($uri, '/');

        $parts = $uri ? explode('/', $uri) : [];

        if (!$parts) {
            $controllerName = 'Controllers\\DashboardController';
            $action = 'index';
            $params = [];
        } else {
            $folder = ucfirst($parts[0]);
            $resource = $parts[1] ?? null;

            $folderPath = dirname(__DIR__) . '/' . $folder;
            if (!is_dir($folderPath)) {
                $lowercaseFolderPath = dirname(__DIR__) . '/' . strtolower($parts[0]);
                if (is_dir($lowercaseFolderPath)) {
                    $folderPath = $lowercaseFolderPath;
                }
            }

            if (is_dir($folderPath) && $resource) {
                $controllerName =
                    $folder . '\\' .
                    ucfirst($resource) . '\\' .
                    ucfirst($resource) . 'Controller';

                $action = $parts[2] ?? 'index';
                $params = array_slice($parts, 3);
            } else {
                $controllerName =
                    'Controllers\\' .
                    $folder . 'Controller';

                $action = $parts[1] ?? 'index';
                $params = array_slice($parts, 2);
            }
        }

        if (!class_exists($controllerName)) {
            http_response_code(404);
            if (str_starts_with($controllerName, 'Api\\')) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'API endpoint not found.']);
                return;
            }
            exit('Controller not found.');
        }

        try {
            $controller = new $controllerName();

            if (!is_callable([$controller, $action])) {
                http_response_code(404);
                if (str_starts_with($controllerName, 'Api\\')) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'API endpoint not found.']);
                    return;
                }
                exit('Page not found.');
            }

            $controller->$action(...$params);
        } catch (\Throwable $e) {
            if (!str_starts_with($controllerName, 'Api\\')) {
                throw $e;
            }

            error_log('Unhandled API request failure in ' . $controllerName . '::' . $action . '.');
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected server error occurred. Please try again.',
            ]);
        }
    }
}
