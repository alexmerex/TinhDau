<?php
/**
 * API: Sắp xếp lại thứ tự các đoạn trong một chuyến
 * 
 * Cách hoạt động:
 * - Nhận danh sách thứ tự mới của các đoạn (theo ___idx)
 * - Hoán đổi vị trí các dòng trong file CSV
 * - Dầu cấp thêm (cap_them=1) sẽ tự động đi theo đoạn của nó (dựa vào created_at gần nhất)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../models/LuuKetQua.php';

try {
    // Lấy dữ liệu từ request
    $input = json_decode(file_get_contents('php://input'), true);
    
    $tenTau = trim($input['ten_tau'] ?? '');
    $soChuyen = (int)($input['so_chuyen'] ?? 0);
    $newOrder = $input['new_order'] ?? []; // Mảng các ___idx theo thứ tự mới
    
    if (empty($tenTau)) {
        throw new Exception('Thiếu tên tàu');
    }
    
    if ($soChuyen <= 0) {
        throw new Exception('Số chuyến không hợp lệ');
    }
    
    if (empty($newOrder) || !is_array($newOrder)) {
        throw new Exception('Danh sách thứ tự mới không hợp lệ');
    }
    
    // Đọc toàn bộ file
    $handle = fopen(KET_QUA_FILE, 'r+');
    if (!$handle) {
        throw new Exception('Không thể mở file dữ liệu');
    }
    
    // Acquire exclusive lock
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new Exception('Không thể khóa file để cập nhật');
    }
    
    // Đọc tất cả các dòng
    $lines = [];
    while (($line = fgets($handle)) !== false) {
        $lines[] = rtrim($line, "\r\n");
    }
    
    if (count($lines) < 2) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new Exception('File dữ liệu trống hoặc không hợp lệ');
    }
    
    $headers = str_getcsv($lines[0]);
    $headerMap = array_flip($headers);
    
    // Chuẩn hóa tên tàu
    $normalize = function($s) {
        $s = trim((string)$s);
        if (preg_match('/^([A-Za-z]+)-0(\d+)$/', $s, $m)) {
            return $m[1] . '-' . $m[2];
        }
        return $s;
    };
    $tenTauNorm = $normalize($tenTau);
    
    // Tìm các đoạn của chuyến này (bao gồm cả cap_them)
    $tripSegments = []; // idx => line content (tất cả đoạn và cấp thêm)

    for ($i = 1; $i < count($lines); $i++) {
        $data = str_getcsv($lines[$i]);
        if (count($data) !== count($headers)) continue;

        $row = array_combine($headers, $data);
        $shipName = $normalize($row['ten_phuong_tien'] ?? '');
        $tripNum = (int)($row['so_chuyen'] ?? 0);

        if ($shipName === $tenTauNorm && $tripNum === $soChuyen) {
            // Bao gồm cả đoạn thường và lệnh cấp thêm (cap_them=1)
            $tripSegments[$i] = $lines[$i];
        }
    }

    // Validate: số lượng đoạn phải khớp (bao gồm cả cấp thêm)
    if (count($newOrder) !== count($tripSegments)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new Exception('Số lượng đoạn trong thứ tự mới (' . count($newOrder) . ') không khớp với số đoạn hiện có (' . count($tripSegments) . ')');
    }

    // Validate: tất cả idx trong newOrder phải tồn tại
    foreach ($newOrder as $idx) {
        if (!isset($tripSegments[$idx])) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new Exception("Đoạn với idx=$idx không tồn tại trong chuyến");
        }
    }
    
    // Lấy vị trí hiện tại của các đoạn
    $currentPositions = array_keys($tripSegments);
    sort($currentPositions);
    
    // Tạo mapping: vị trí cũ -> nội dung mới
    $newSegmentLines = [];
    foreach ($newOrder as $i => $oldIdx) {
        $newPos = $currentPositions[$i];
        $newSegmentLines[$newPos] = $tripSegments[$oldIdx];
    }
    
    // Cập nhật các dòng
    foreach ($newSegmentLines as $pos => $content) {
        $lines[$pos] = $content;
    }

    // Ghi lại file
    rewind($handle);
    ftruncate($handle, 0);

    foreach ($lines as $line) {
        fwrite($handle, $line . PHP_EOL);
    }

    // Release lock và đóng file
    flock($handle, LOCK_UN);
    fclose($handle);

    echo json_encode([
        'success' => true,
        'message' => 'Đã sắp xếp lại thứ tự các đoạn trong chuyến ' . $soChuyen,
        'segments_reordered' => count($newOrder)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

