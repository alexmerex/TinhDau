<?php
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
    $insertPosition = (int)($_POST['insert_position'] ?? 0);

    if (empty($tenTau)) {
        throw new Exception('Thiếu tên tàu');
    }

    if ($insertPosition <= 0) {
        throw new Exception('Vị trí insert phải là số dương');
    }

    $luuKetQua = new LuuKetQua();
    $allData = $luuKetQua->docTatCa();

    $affectedRows = [];
    $allTrips = [];

    foreach ($allData as $row) {
        if (trim($row['ten_phuong_tien']) === $tenTau) {
            $soChuyen = (int)($row['so_chuyen'] ?? 0);
            $allTrips[] = $soChuyen;

            if ($soChuyen >= $insertPosition) {
                $affectedRows[] = [
                    'idx' => (int)($row['___idx'] ?? 0),
                    'old_trip' => $soChuyen,
                    'new_trip' => $soChuyen + 1
                ];
            }
        }
    }

    $allTrips = array_unique($allTrips);
    sort($allTrips);

    $maxTrip = empty($allTrips) ? 0 : max($allTrips);
    if ($insertPosition > $maxTrip + 1) {
        throw new Exception("Không thể insert chuyến $insertPosition. Chuyến cao nhất hiện tại là $maxTrip");
    }

    if (empty($affectedRows)) {
        echo json_encode([
            'success' => true,
            'message' => "Vị trí chuyến $insertPosition đã sẵn sàng để tạo mới",
            'affected_count' => 0,
            'insert_position' => $insertPosition,
            'renumbered_trips' => []
        ]);
        exit;
    }

    usort($affectedRows, function($a, $b) {
        return $b['idx'] <=> $a['idx'];
    });

    $handle = fopen(KET_QUA_FILE, 'r+');
    if (!$handle) {
        throw new Exception('Không thể mở file dữ liệu');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new Exception('Không thể khóa file');
    }

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

    $updatedCount = 0;
    foreach ($affectedRows as $affected) {
        $idx = $affected['idx'];
        if ($idx > 0 && $idx < count($lines)) {
            $rowData = str_getcsv($lines[$idx]);
            $rowData[$soChuyenIndex] = $affected['new_trip'];

            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, $rowData);
            rewind($fh);
            $csvLine = stream_get_contents($fh);
            fclose($fh);

            $lines[$idx] = rtrim($csvLine, "\r\n");
            $updatedCount++;
        }
    }

    rewind($handle);
    ftruncate($handle, 0);

    foreach ($lines as $line) {
        fwrite($handle, $line . PHP_EOL);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    $renumberedTrips = array_map(function($item) {
        return $item['old_trip'] . ' → ' . $item['new_trip'];
    }, $affectedRows);

    echo json_encode([
        'success' => true,
        'message' => "Đã renumber thành công $updatedCount bản ghi",
        'affected_count' => $updatedCount,
        'insert_position' => $insertPosition,
        'renumbered_trips' => $renumberedTrips
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
