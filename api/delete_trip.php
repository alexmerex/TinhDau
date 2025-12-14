<?php
/**
 * API: Xóa chuyến và renumber các chuyến phía sau
 *
 * Cách hoạt động:
 * - Xóa tất cả bản ghi của chuyến được chọn
 * - Giảm số chuyến của tất cả các chuyến > số chuyến bị xóa đi 1
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LuuKetQua.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Chỉ chấp nhận POST request']);
    exit;
}

try {
    $tenTau = trim($_POST['ten_tau'] ?? '');
    $deleteTrip = (int)($_POST['delete_trip'] ?? 0);

    if (empty($tenTau)) {
        throw new Exception('Thiếu tên tàu');
    }

    if ($deleteTrip <= 0) {
        throw new Exception('Số chuyến phải là số dương');
    }

    $luuKetQua = new LuuKetQua();
    $allData = $luuKetQua->docTatCa();

    // Chuẩn hóa tên tàu để so sánh
    $normalize = function($s) {
        $s = trim((string)$s);
        if (preg_match('/^([A-Za-z]+)-0(\d+)$/', $s, $m)) {
            return $m[1] . '-' . $m[2];
        }
        return $s;
    };
    $tenTauNorm = $normalize($tenTau);

    $rowsToDelete = [];  // Các dòng cần xóa
    $rowsToRenumber = []; // Các dòng cần đổi số chuyến
    $allTrips = [];

    foreach ($allData as $row) {
        $shipName = $normalize($row['ten_phuong_tien'] ?? '');
        if ($shipName === $tenTauNorm) {
            $soChuyen = (int)($row['so_chuyen'] ?? 0);
            $idx = (int)($row['___idx'] ?? 0);
            $allTrips[] = $soChuyen;

            if ($soChuyen === $deleteTrip) {
                // Chuyến cần xóa
                $rowsToDelete[] = $idx;
            } elseif ($soChuyen > $deleteTrip) {
                // Chuyến cần giảm số
                $rowsToRenumber[] = [
                    'idx' => $idx,
                    'old_trip' => $soChuyen,
                    'new_trip' => $soChuyen - 1
                ];
            }
        }
    }

    $allTrips = array_unique($allTrips);
    sort($allTrips);

    // Kiểm tra chuyến có tồn tại không
    if (!in_array($deleteTrip, $allTrips)) {
        throw new Exception("Chuyến $deleteTrip không tồn tại");
    }

    if (empty($rowsToDelete)) {
        throw new Exception("Không tìm thấy dữ liệu của chuyến $deleteTrip");
    }

    // Mở file để sửa
    $handle = fopen(KET_QUA_FILE, 'r+');
    if (!$handle) {
        throw new Exception('Không thể mở file dữ liệu');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new Exception('Không thể khóa file');
    }

    // Đọc tất cả các dòng
    $lines = [];
    while (($line = fgets($handle)) !== false) {
        $lines[] = rtrim($line, "\r\n");
    }

    $headers = str_getcsv($lines[0]);
    $soChuyenIndex = array_search('so_chuyen', $headers);

    if ($soChuyenIndex === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new Exception('Không tìm thấy cột so_chuyen');
    }

    // Đánh dấu các dòng cần xóa
    $deleteSet = array_flip($rowsToDelete);

    // Renumber các chuyến cần đổi số
    foreach ($rowsToRenumber as $item) {
        $idx = $item['idx'];
        if ($idx > 0 && $idx < count($lines) && !isset($deleteSet[$idx])) {
            $rowData = str_getcsv($lines[$idx]);
            $rowData[$soChuyenIndex] = $item['new_trip'];

            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, $rowData);
            rewind($fh);
            $csvLine = stream_get_contents($fh);
            fclose($fh);

            $lines[$idx] = rtrim($csvLine, "\r\n");
        }
    }

    // Tạo mảng mới loại bỏ các dòng bị xóa
    $newLines = [$lines[0]]; // Header
    for ($i = 1; $i < count($lines); $i++) {
        if (!isset($deleteSet[$i])) {
            $newLines[] = $lines[$i];
        }
    }

    // Ghi lại file
    rewind($handle);
    ftruncate($handle, 0);

    foreach ($newLines as $line) {
        fwrite($handle, $line . PHP_EOL);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    $renumberedTrips = array_map(function($item) {
        return $item['old_trip'] . ' → ' . $item['new_trip'];
    }, $rowsToRenumber);

    echo json_encode([
        'success' => true,
        'message' => "Đã xóa chuyến $deleteTrip với " . count($rowsToDelete) . " bản ghi",
        'deleted_count' => count($rowsToDelete),
        'renumbered_count' => count($rowsToRenumber),
        'renumbered_trips' => $renumberedTrips
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
