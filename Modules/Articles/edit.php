<?php

declare(strict_types=1);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// Chuyển hướng sang giao diện Editor thống nhất trên index.php theo quy định bôi đỏ
if ($id) {
    header('Location: /index.php?edit_id=' . $id);
} else {
    header('Location: /index.php');
}
exit;
