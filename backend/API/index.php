<?php
declare(strict_types=1);

use App\API\Http\ApiException;
use App\API\Http\Request;
use App\API\Http\Response;
use App\API\Kernel;

[$backendRoot] = require __DIR__ . '/bootstrap.php';

try {
    (new Kernel($backendRoot))->dispatch(new Request());
} catch (ApiException $error) {
    Response::error($error->getMessage(), $error->errorCode, $error->status);
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 'INVALID_INPUT', 422);
} catch (PDOException $error) {
    error_log((string) $error);
    if (str_contains(strtolower($error->getMessage()), 'unique')) {
        Response::error('Dữ liệu đã tồn tại.', 'CONFLICT', 409);
    }
    Response::error('Database tạm thời không khả dụng.', 'DATABASE_ERROR', 503);
} catch (MongoDB\Driver\Exception\BulkWriteException $error) {
    error_log((string) $error);
    $writeErrors = $error->getWriteResult()->getWriteErrors();
    foreach ($writeErrors as $writeError) {
        if ($writeError->getCode() === 11000) {
            Response::error('Dữ liệu đã tồn tại.', 'CONFLICT', 409);
        }
        if ($writeError->getCode() === 121) {
            Response::error('Dữ liệu không đáp ứng schema database.', 'VALIDATION_ERROR', 422);
        }
    }
    Response::error('Không thể ghi dữ liệu MongoDB.', 'MONGODB_WRITE_ERROR', 500);
} catch (MongoDB\Driver\Exception\Exception $error) {
    error_log((string) $error);
    Response::error('MongoDB tạm thời không khả dụng.', 'MONGODB_ERROR', 503);
} catch (Throwable $error) {
    error_log((string) $error);
    Response::error('Đã xảy ra lỗi máy chủ.', 'INTERNAL_ERROR', 500);
}
