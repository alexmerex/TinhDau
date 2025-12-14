<?php
/**
 * AJAX endpoint để lấy danh sách chuyến của một tàu
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../models/LuuKetQua.php';
require_once '../models/TauPhanLoai.php';
require_once '../models/HeSoTau.php';

if (!isset($_GET['ten_tau']) || trim((string)$_GET['ten_tau']) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tên tàu không được để trống']);
    exit;
}

try {
    $luuKetQua = new LuuKetQua();
    $tenTau = trim((string)$_GET['ten_tau']);

    // Validate ship exists
    $hs = new HeSoTau();
    if (!$hs->isTauExists($tenTau)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tàu không tồn tại']);
        exit;
    }
    
    // Lấy danh sách tất cả các chuyến của tàu này
    $trips = $luuKetQua->getDanhSachChuyenCuaTau($tenTau);

    // Lấy mã chuyến cao nhất
    $maxTrip = $luuKetQua->layMaChuyenCaoNhat($tenTau);

    // FIX: Fill gaps - thêm các chuyến trống vào danh sách
    // Nếu có chuyến 1,2,3,6,7 thì thêm 4,5 vào để user có thể nhập
    if (!empty($trips) && $maxTrip > 0) {
        // Tạo danh sách đầy đủ từ 1 đến maxTrip
        $fullTrips = range(1, $maxTrip);

        // Merge với trips hiện có
        $trips = array_values(array_unique(array_merge($trips, $fullTrips)));
        sort($trips);
    }

    $tauModel = new TauPhanLoai();
    $soDangKy = $tauModel->getSoDangKy($tenTau);
    echo json_encode([
        'success' => true,
        'trips' => $trips,
        'max_trip' => $maxTrip,
        'next_trip' => $maxTrip + 1,
        'so_dang_ky' => $soDangKy
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
