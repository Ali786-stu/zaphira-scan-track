<?php
/**
 * API Error Handler
 * Provides standardized error responses for HTTP errors
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/utils/helpers.php';

// Get the HTTP status code
$http_code = http_response_code();

// Handle CORS preflight request
handle_cors();

// Set appropriate headers for API responses
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Define error messages for different HTTP status codes
$error_messages = [
    400 => [
        'error' => 'Bad Request',
        'error_code' => 'BAD_REQUEST',
        'message' => 'The request is invalid or cannot be processed.'
    ],
    401 => [
        'error' => 'Unauthorized',
        'error_code' => 'UNAUTHORIZED',
        'message' => 'Authentication is required to access this resource.'
    ],
    403 => [
        'error' => 'Forbidden',
        'error_code' => 'FORBIDDEN',
        'message' => 'You do not have permission to access this resource.'
    ],
    404 => [
        'error' => 'Not Found',
        'error_code' => 'NOT_FOUND',
        'message' => 'The requested resource was not found.'
    ],
    405 => [
        'error' => 'Method Not Allowed',
        'error_code' => 'METHOD_NOT_ALLOWED',
        'message' => 'The HTTP method is not allowed for this resource.'
    ],
    408 => [
        'error' => 'Request Timeout',
        'error_code' => 'REQUEST_TIMEOUT',
        'message' => 'The request timed out. Please try again.'
    ],
    409 => [
        'error' => 'Conflict',
        'error_code' => 'CONFLICT',
        'message' => 'The request could not be completed due to a conflict.'
    ],
    410 => [
        'error' => 'Gone',
        'error_code' => 'GONE',
        'message' => 'The requested resource is no longer available.'
    ],
    413 => [
        'error' => 'Payload Too Large',
        'error_code' => 'PAYLOAD_TOO_LARGE',
        'message' => 'The request entity is larger than the server is willing or able to process.'
    ],
    415 => [
        'error' => 'Unsupported Media Type',
        'error_code' => 'UNSUPPORTED_MEDIA_TYPE',
        'message' => 'The request entity has a media type which the server or resource does not support.'
    ],
    422 => [
        'error' => 'Unprocessable Entity',
        'error_code' => 'UNPROCESSABLE_ENTITY',
        'message' => 'The request was well-formed but unable to be followed due to semantic errors.'
    ],
    429 => [
        'error' => 'Too Many Requests',
        'error_code' => 'RATE_LIMIT_EXCEEDED',
        'message' => 'Too many requests have been made. Please try again later.'
    ],
    500 => [
        'error' => 'Internal Server Error',
        'error_code' => 'INTERNAL_SERVER_ERROR',
        'message' => 'An unexpected error occurred on the server.'
    ],
    501 => [
        'error' => 'Not Implemented',
        'error_code' => 'NOT_IMPLEMENTED',
        'message' => 'The server does not support the functionality required to fulfill the request.'
    ],
    502 => [
        'error' => 'Bad Gateway',
        'error_code' => 'BAD_GATEWAY',
        'message' => 'The server received an invalid response from the upstream server.'
    ],
    503 => [
        'error' => 'Service Unavailable',
        'error_code' => 'SERVICE_UNAVAILABLE',
        'message' => 'The server is currently unable to handle the request.'
    ],
    504 => [
        'error' => 'Gateway Timeout',
        'error_code' => 'GATEWAY_TIMEOUT',
        'message' => 'The server did not receive a timely response from the upstream server.'
    ],
    507 => [
        'error' => 'Insufficient Storage',
        'error_code' => 'INSUFFICIENT_STORAGE',
        'message' => 'The server is unable to store the representation needed to complete the request.'
    ]
];

// Get the error details for the current status code
$error_details = $error_messages[$http_code] ?? [
    'error' => 'Unknown Error',
    'error_code' => 'UNKNOWN_ERROR',
    'message' => 'An unknown error occurred.'
];

// Build the error response
$response = [
    'success' => false,
    'error' => $error_details['error'],
    'error_code' => $error_details['error_code'],
    'message' => $error_details['message'],
    'timestamp' => date('c'),
    'status_code' => $http_code
];

// Add additional debugging information in development mode
if (($_ENV['APP_ENV'] ?? 'development') === 'development' && $http_code >= 500) {
    $response['debug'] = [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
}

// Log the error for debugging
error_log("API Error [$http_code]: " . $error_details['error'] . " - " . $_SERVER['REQUEST_URI'] ?? 'unknown');

// Send JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Exit to prevent any further output
exit;

?>