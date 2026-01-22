<?php
// Custom error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log('FATAL ERROR: ' . print_r($error, true));
    }
});

/*
 * Trang chính - Form tính toán nhiên liệu sử dụng cho tàu
 * Cho phép người dùng nhập thông tin tàu, điểm bắt đầu, điểm kết thúc và khối lượng
 */

// Kiểm tra đăng nhập
require_once __DIR__ . '/auth/check_auth.php';

require_once 'includes/helpers.php';
require_once 'config/database.php';
require_once 'models/TinhToanNhienLieu.php';
require_once 'models/LuuKetQua.php';
require_once 'models/TauPhanLoai.php';
require_once 'models/CayXang.php';
require_once 'models/DauTon.php';
require_once 'models/LoaiHang.php';

// Khởi tạo đối tượng tính toán
$tinhToan = new TinhToanNhienLieu();
$luuKetQua = new LuuKetQua();
$tauPhanLoai = new TauPhanLoai();
$cayXang = new CayXang();
$dauTon = new DauTon();

// Lấy danh sách tàu, điểm và cây xăng
$danhSachTau = $tinhToan->getDanhSachTau();
$danhSachDiem = $tinhToan->getDanhSachDiem();
$danhSachCayXang = $cayXang->getAll();
$loaiHangModel = new LoaiHang();
$danhSachLoaiHang = $loaiHangModel->getAll();

// Xử lý form submit
$ketQua = null;
$error = null;
$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
$formData = [
    'ten_tau' => '',
    'so_chuyen' => '',
    'chuyen_moi' => 0,
    'thang_bao_cao' => date('Y-m'),
    'diem_bat_dau' => '',
    'diem_ket_thuc' => '',
    'doi_lenh' => 0,
    'diem_moi' => '',
    'diem_moi_list' => [],
    'khoang_cach_thuc_te' => '',
    'khoi_luong' => '',
    'ngay_di' => '',
    'ngay_den' => '',
    'ngay_do_xong' => '',
    'loai_hang' => '',
    'cap_them' => 0,
    'loai_cap_them' => 'bom_nuoc',
    'dia_diem_cap_them' => '',
    'ly_do_cap_them' => '',
    'ly_do_cap_them_khac' => '',
    'so_luong_cap_them' => '',
    'ghi_chu' => ''
];

// Biến để lưu thông tin chuyến hiện tại và các đoạn
$chuyenHienTai = null;
$cacDoanCuaChuyen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'calculate';
        $tenTau = $_POST['ten_tau'] ?? '';
        $soChuyen = trim($_POST['so_chuyen'] ?? '');
        $chuyenMoi = isset($_POST['chuyen_moi']) ? 1 : 0;
        $thangBaoCao = $_POST['thang_bao_cao'] ?? date('Y-m');
        

        $diemBatDau = trim($_POST['diem_bat_dau'] ?? '');
        $diemKetThuc = trim($_POST['diem_ket_thuc'] ?? '');
        $khoiLuong = floatval($_POST['khoi_luong'] ?? 0);

        // Lấy ghi chú cho các điểm
        $ghiChuDiemBatDau = trim($_POST['ghi_chu_diem_bat_dau'] ?? '');
        $ghiChuDiemKetThuc = trim($_POST['ghi_chu_diem_ket_thuc'] ?? '');
        $ghiChuDiemMoi = trim($_POST['ghi_chu_diem_moi'] ?? '');

        // Ghép ghi chú vào tên điểm nếu có (dùng ngoặc fullwidth để phân biệt)
        if (!empty($ghiChuDiemBatDau)) {
            $diemBatDau .= ' （' . $ghiChuDiemBatDau . '）';
        }
        if (!empty($ghiChuDiemKetThuc)) {
            $diemKetThuc .= ' （' . $ghiChuDiemKetThuc . '）';
        }
        $ngayDiRaw = trim($_POST['ngay_di'] ?? '');
        $ngayDi = parse_date_vn($ngayDiRaw);
        $ngayDen = parse_date_vn($_POST['ngay_den'] ?? '');
        $ngayDoXong = parse_date_vn($_POST['ngay_do_xong'] ?? '');
        $loaiHang = trim($_POST['loai_hang'] ?? '');
        // Fix: Kiểm tra giá trị thay vì chỉ kiểm tra isset (vì hidden input luôn tồn tại)
        $capThem = (isset($_POST['cap_them']) && $_POST['cap_them'] == '1') ? 1 : 0;
        $doiLenh = isset($_POST['doi_lenh']) ? 1 : 0;
        // Hỗ trợ Đổi lệnh đa điểm: nhận mảng điểm mới
        $rawDiemMoi = $_POST['diem_moi'] ?? [];
        if (!is_array($rawDiemMoi)) {
            $rawDiemMoi = [$rawDiemMoi];
        }
        $rawLyDoDiemMoi = $_POST['diem_moi_reason'] ?? [];
        if (!is_array($rawLyDoDiemMoi)) {
            $rawLyDoDiemMoi = [$rawLyDoDiemMoi];
        }
        $structuredDiemMoi = [];
        $dsDiemMoi = [];
        $dsDiemMoiGoc = [];
        $lastPoint = '';
        foreach ($rawDiemMoi as $idx => $value) {
            $point = trim((string)$value);
            if ($point === '') {
                continue;
            }
            $reason = isset($rawLyDoDiemMoi[$idx]) ? trim((string)$rawLyDoDiemMoi[$idx]) : '';
            // Tách lý do nằm trong ngoặc đối với dữ liệu cũ (ví dụ: "Cảng X (Đổi lệnh)")
            if ($reason === '') {
                // Xử lý ngoặc chuẩn ()
                if (preg_match('/^(.*)\(([^()]*)\)\s*$/u', $point, $matches)) {
                    $candidatePoint = trim($matches[1]);
                    $candidateReason = trim($matches[2]);
                    if (mb_stripos($candidateReason, 'đổi lệnh') !== false || mb_stripos($candidateReason, 'lãnh vật tư') !== false) {
                        $point = $candidatePoint;
                        $reason = $candidateReason;
                    }
                }
                // Xử lý ngoặc full-width （）
                if (preg_match('/^(.*)（([^（）]*)）\s*$/u', $point, $matchesFull)) {
                    $candidatePoint = trim($matchesFull[1]);
                    $candidateReason = trim($matchesFull[2]);
                    if (mb_stripos($candidateReason, 'đổi lệnh') !== false || mb_stripos($candidateReason, 'lãnh vật tư') !== false) {
                        $point = $candidatePoint;
                        $reason = $candidateReason;
                    }
                }
            }
            $structuredEntry = [
                'point' => $point,
                'reason' => $reason,
                'note' => ''
            ];
            $displayPoint = $point;
            if ($reason !== '') {
                $displayPoint .= ' (' . $reason . ')';
            }
            $structuredDiemMoi[] = $structuredEntry;
            $dsDiemMoi[] = $displayPoint;
            $lastPoint = $point;
            $dsDiemMoiGoc[] = $point;
        }
        // Ghép ghi chú vào điểm cuối nếu có (dùng ngoặc fullwidth để phân biệt)
        if (!empty($ghiChuDiemMoi) && !empty($dsDiemMoi)) {
            $lastIdx = count($dsDiemMoi) - 1;
            $dsDiemMoi[$lastIdx] .= ' （' . $ghiChuDiemMoi . '）';
            $structuredDiemMoi[$lastIdx]['note'] = $ghiChuDiemMoi;
        }
        // Chuỗi điểm mới để hiển thị/lưu
        $diemMoi = implode(' → ', $dsDiemMoi);
        if (!empty($structuredDiemMoi)) {
            $lastEntry = end($structuredDiemMoi);
            if ($lastEntry && isset($lastEntry['point'])) {
                $lastPoint = $lastEntry['point'];
            }
        }
        if ($lastPoint === '') {
            $lastPoint = $diemKetThuc;
        }
        $khoangCachThucTe = isset($_POST['khoang_cach_thuc_te']) && $_POST['khoang_cach_thuc_te'] !== '' ? floatval($_POST['khoang_cach_thuc_te']) : null;
        // Lấy khoảng cách thủ công (chỉ dùng khi không có tuyến trực tiếp và không đổi lệnh)
        $khoangCachThuCong = isset($_POST['khoang_cach_thu_cong']) && $_POST['khoang_cach_thu_cong'] !== '' ? floatval($_POST['khoang_cach_thu_cong']) : null;

        // Xử lý lý do cấp thêm
        $loaiCapThem = trim($_POST['loai_cap_them'] ?? 'bom_nuoc');
        $diaDiemCapThem = trim($_POST['dia_diem_cap_them'] ?? '');
        $lyDoCapThemKhac = trim($_POST['ly_do_cap_them_khac'] ?? '');
        $soLuongCapThem = floatval($_POST['so_luong_cap_them'] ?? 0);

        // Tạo chuỗi lý do cấp thêm tự động
        $lyDoCapThem = '';
        if ($capThem) {
            if ($loaiCapThem === 'bom_nuoc') {
                $lyDoCapThem = "Dầu ma nơ tại bến " . $diaDiemCapThem . " 01 chuyến";
            } elseif ($loaiCapThem === 'qua_cau') {
                $lyDoCapThem = "Dầu bơm nước qua cầu " . $diaDiemCapThem . " 01 chuyến";
            } elseif ($loaiCapThem === 'ro_dai_ve_sinh') {
                $lyDoCapThem = "Dầu rô đai+ vệ sinh 01 máy chính";
            } else {
                // Loại khác - dùng lý do tự nhập
                $lyDoCapThem = $lyDoCapThemKhac;
            }
            // Thêm số lượng vào cuối lý do nếu có
            if (!empty($lyDoCapThem) && $soLuongCapThem > 0) {
                $lyDoCapThem .= " x " . number_format($soLuongCapThem, 0) . " lít";
            }
        }
        $ghiChu = trim($_POST['ghi_chu'] ?? '');

        // Logic xác định mã chuyến được làm rõ
        if ($action === 'save' || $action === 'calculate') {
            if ($chuyenMoi && !empty($tenTau)) {
                $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($tenTau);
                $soChuyen = $maChuyenCaoNhat + 1;
            } elseif (empty($soChuyen) || !is_numeric($soChuyen)) {
                // Nếu không ở chế độ tạo mới và không có mã chuyến hợp lệ, đây là lỗi
                if ($action === 'save') {
                    throw new Exception('Không có mã chuyến hợp lệ để lưu.');
                }
                // Đối với 'calculate', có thể cho qua để chỉ hiển thị tính toán
            }
        }
        


        // Xử lý logic ngày cho cấp thêm: tự động link theo ngày chuyến trước đó
        if ($capThem && !empty($tenTau) && !empty($soChuyen)) {
            $ngayChuyenTruoc = $luuKetQua->layNgayChuyenTruoc($tenTau, (int)$soChuyen);
            if ($ngayChuyenTruoc !== '') {
                // Chuyển đổi từ format VN sang ISO để lưu
                $ngayChuyenTruocIso = parse_date_vn($ngayChuyenTruoc);
                if ($ngayChuyenTruocIso) {
                    $ngayDi = $ngayChuyenTruocIso;
                }
            }
        }

        // Lưu lại dữ liệu form để hiển thị lại sau redirect
        // Reset chuyen_moi về false sau khi lưu để tránh tự động tạo chuyến mới
        $formData = [
            'ten_tau' => $tenTau,
            'so_chuyen' => $soChuyen,
            'chuyen_moi' => 0, // Luôn reset về false sau khi lưu
            'thang_bao_cao' => $thangBaoCao,
            'diem_bat_dau' => $diemBatDau,
            'diem_ket_thuc' => $diemKetThuc,
            'doi_lenh' => $doiLenh,
            'diem_moi' => $diemMoi,
            'diem_moi_list' => $doiLenh ? $structuredDiemMoi : [],
            'khoang_cach_thuc_te' => ($khoangCachThucTe === null ? '' : (string)$khoangCachThucTe),
            'khoi_luong' => ($khoiLuong === 0.0 ? '0' : (string)$khoiLuong),
            'ngay_di' => $ngayDi,
            'ngay_den' => $ngayDen,
            'ngay_do_xong' => $ngayDoXong,
            'loai_hang' => $loaiHang,
            'cap_them' => $capThem,
            'loai_cap_them' => $loaiCapThem,
            'dia_diem_cap_them' => $diaDiemCapThem,
            'ly_do_cap_them' => $lyDoCapThem,
            'ly_do_cap_them_khac' => $lyDoCapThemKhac,
            'so_luong_cap_them' => ($soLuongCapThem === 0.0 ? '' : (string)$soLuongCapThem),  // FIX: Dùng '' thay vì '0' để tránh vi phạm min="0.01"
            'ghi_chu' => $ghiChu
        ];

        // Thực hiện tính toán
        // Biến lưu kết quả cấp thêm (nếu có)
        $ketQuaCapThem = null;

        // Xử lý tính toán dầu cho quảng đường (nếu có đủ thông tin)
        $coThongTinTuyenDuong = !empty($diemBatDau) && !empty($diemKetThuc);

        if ($coThongTinTuyenDuong) {
            // Có thông tin tuyến đường -> tính toán dầu cho quảng đường
            // Tách tên điểm gốc (loại bỏ ghi chú trong ngoặc fullwidth) để tính toán
            $diemBatDauGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemBatDau);
            $diemKetThucGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemKetThuc);
            // Với đổi lệnh đa điểm, lấy điểm cuối cùng để tính, nhưng hiển thị toàn chuỗi
            if (!empty($dsDiemMoiGoc)) {
                $dsDiemMoiGoc = array_values($dsDiemMoiGoc);
            } else {
                $dsDiemMoiGoc = array_map(function($p){
                    $p = preg_replace('/\s*（[^）]*）\s*$/u', '', (string)$p);
                    $p = preg_replace('/\s*\([^()]*\)\s*$/u', '', (string)$p);
                    return trim($p);
                }, ($dsDiemMoi ?? []));
            }
            $diemMoiGoc = !empty($dsDiemMoiGoc) ? end($dsDiemMoiGoc) : '';

            // Tính toán bình thường sử dụng tên điểm gốc
            if ($doiLenh) {
                if (empty($diemMoiGoc)) {
                    throw new Exception('Vui lòng nhập ít nhất một Điểm đến mới (C, D, ...).');
                }
                $ketQua = $tinhToan->tinhNhienLieuDoiLenh($tenTau, $diemBatDauGoc, $diemKetThucGoc, $diemMoiGoc, $khoiLuong, $khoangCachThucTe ?? 0);
                // Ghi đè hiển thị tuyến để thể hiện đầy đủ các điểm đổi lệnh
                if (is_array($ketQua)) {
                    $routeSegments = [];
                    $routeSegments[] = $diemBatDau;
                    $routeSegments[] = $diemKetThuc;
                    if (!empty($structuredDiemMoi)) {
                        foreach ($structuredDiemMoi as $entry) {
                            $label = $entry['point'];
                            $suffixParts = [];
                            if (!empty($entry['reason'])) {
                                $suffixParts[] = $entry['reason'];
                            }
                            if (!empty($entry['note'])) {
                                $suffixParts[] = $entry['note'];
                            }
                            if (!empty($suffixParts)) {
                                $label .= ' (' . implode(' – ', $suffixParts) . ')';
                            }
                            $routeSegments[] = $label;
                        }
                    }
                    $routeHienThi = implode(' → ', array_filter($routeSegments, function($part){
                        return trim((string)$part) !== '';
                    }));
                    $ketQua['thong_tin']['route_hien_thi'] = $routeHienThi;
                }
            } else {
                // Chỉ tính theo tuyến có sẵn; nếu thiếu tuyến sẽ báo hướng dẫn thêm tuyến
                // HOẶC sử dụng khoảng cách thủ công nếu người dùng đã nhập
                try {
                    $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemBatDauGoc, $diemKetThucGoc, $khoiLuong, $khoangCachThuCong);
                } catch (Exception $e) {
                    $link = '<a href="admin/quan_ly_tuyen_duong.php">Quản lý tuyến đường</a>';
                    throw new Exception('Chưa có tuyến trực tiếp giữa "' . $diemBatDauGoc . '" và "' . $diemKetThucGoc . '". Vui lòng vào ' . $link . ' để thêm tuyến hoặc nhập khoảng cách thủ công.');
                }
            }
        } else {
            // Không có thông tin tuyến đường -> chỉ có thể là cấp thêm
            $ketQua = null;
        }

        // Xử lý cấp thêm (nếu có)
        if ($capThem && $soLuongCapThem > 0) {
            $ketQuaCapThem = [
                'nhien_lieu_lit' => $soLuongCapThem,
                'loai_tinh' => 'cap_them',
                'thong_tin' => [
                    'ten_tau' => $tenTau,
                    'diem_bat_dau' => '',
                    'diem_ket_thuc' => '',
                    'khoang_cach_km' => 0,
                    'khoi_luong_tan' => 0,
                    'he_so_ko_hang' => 0,
                    'he_so_co_hang' => 0,
                    'nhom_cu_ly' => '',
                    'nhom_cu_ly_label' => 'Cấp thêm'
                ],
                'chi_tiet' => [
                    'sch' => 0,
                    'skh' => 0,
                    'cong_thuc' => $lyDoCapThem
                ]
            ];

            // Nếu không có kết quả tính toán dầu cho quảng đường, hiển thị cấp thêm
            if (!$ketQua) {
                $ketQua = $ketQuaCapThem;
            }
        }

        // Kiểm tra phải có ít nhất một kết quả
        if (!$ketQua && !$ketQuaCapThem) {
            throw new Exception('Vui lòng nhập thông tin tuyến đường hoặc cấp thêm dầu.');
        }

        // Xác định created_at theo quy tắc tháng báo cáo và/hoặc ngày đã nhập
        // - Nếu có bất kỳ ngày nào: lấy ngày MUỘN NHẤT trong số (ngày đi/đến/dỡ xong)
        // - Nếu không có ngày: dùng giữa tháng của 'thang_bao_cao' (ngày 15)
        $createdAt = date('Y-m-d H:i:s');
        $dsNgay = array_values(array_filter([$ngayDi, $ngayDen, $ngayDoXong], function($d){ return !empty($d); }));
        if (!empty($dsNgay)) {
            // Convert dates to timestamps for proper comparison
            $timestamps = array_map(function($date) {
                return strtotime($date);
            }, $dsNgay);
            
            // Filter out invalid timestamps
            $validTimestamps = array_filter($timestamps, function($ts) {
                return $ts !== false;
            });
            
            if (!empty($validTimestamps)) {
                // Lấy ngày muộn nhất để đảm bảo ghi nhận đúng tháng phát sinh gần nhất
                $maxTimestamp = max($validTimestamps);
                $createdAt = date('Y-m-d H:i:s', $maxTimestamp);
            }
        } elseif (!empty($thangBaoCao) && preg_match('/^\d{4}-\d{2}$/', $thangBaoCao)) {
            // Không có ngày nào: rơi về tháng báo cáo do người dùng chọn
            $createdAt = $thangBaoCao . '-15 ' . date('H:i:s');
        }

        // Chuẩn bị dữ liệu lưu
        // Xác định có lưu cả hai kết quả không
        $luuCaHaiKetQua = ($ketQua && $ketQuaCapThem && $ketQua['loai_tinh'] !== 'cap_them');

        // Dữ liệu chung cho cả hai loại
        $dataChung = [
            'ten_phuong_tien' => $tenTau,
            'so_chuyen' => $soChuyen,
            'ghi_chu' => $ghiChu,
            'ngay_di' => $ngayDi,
            'ngay_den' => $ngayDen,
            'ngay_do_xong' => $ngayDoXong,
            'loai_hang' => $loaiHang,
            'thang_bao_cao' => $thangBaoCao,
            'created_at' => $createdAt,
        ];

        // Chuẩn bị dữ liệu tính toán dầu (nếu có)
        $dataLuuTinhToan = null;
        if ($ketQua && $ketQua['loai_tinh'] !== 'cap_them') {
            $sch = $ketQua['chi_tiet']['sch'] ?? 0;
            $skh = $ketQua['chi_tiet']['skh'] ?? 0;
            $heSoKhongHang = $ketQua['thong_tin']['he_so_ko_hang'] ?? 0;
            $heSoCoHang = $ketQua['thong_tin']['he_so_co_hang'] ?? 0;
            $nhienLieuLit = $ketQua['nhien_lieu_lit'] ?? 0;
            $khoiLuongLuanChuyen = ($sch > 0 && $khoiLuong > 0) ? ($sch * $khoiLuong) : 0;

            $dataLuuTinhToan = array_merge($dataChung, [
                'diem_di' => $diemBatDau,
                'diem_den' => (!empty($doiLenh) ? $lastPoint : $diemKetThuc),
                'cu_ly_co_hang_km' => $sch,
                'cu_ly_khong_hang_km' => $skh,
                'he_so_co_hang' => $heSoCoHang,
                'he_so_khong_hang' => $heSoKhongHang,
                'khoi_luong_van_chuyen_t' => $khoiLuong,
                'khoi_luong_luan_chuyen' => $khoiLuongLuanChuyen,
                'dau_tinh_toan_lit' => $nhienLieuLit,
                'cap_them' => 0, // Không phải cấp thêm
                'doi_lenh' => $doiLenh,
                'diem_du_kien' => $diemKetThuc,
                'ly_do_cap_them' => '',
                'so_luong_cap_them_lit' => 0,
                'cay_xang_cap_them' => '',
                'nhom_cu_ly' => $ketQua['thong_tin']['nhom_cu_ly'] ?? '',
                'doi_lenh_tuyen' => (!empty($doiLenh) ? json_encode($structuredDiemMoi, JSON_UNESCAPED_UNICODE) : ''),
                'route_hien_thi' => ($ketQua['thong_tin']['route_hien_thi'] ?? '') ?: ($diemBatDau . ' → ' . $diemKetThuc),
            ]);
        }

        // Chuẩn bị dữ liệu cấp thêm (nếu có)
        $dataLuuCapThem = null;
        if ($ketQuaCapThem) {
            $dataLuuCapThem = array_merge($dataChung, [
                'diem_di' => '',
                'diem_den' => '',
                'cu_ly_co_hang_km' => 0,
                'cu_ly_khong_hang_km' => 0,
                'he_so_co_hang' => 0,
                'he_so_khong_hang' => 0,
                'khoi_luong_van_chuyen_t' => 0,
                'khoi_luong_luan_chuyen' => 0,
                'dau_tinh_toan_lit' => $soLuongCapThem, // FIX: Ghi số lượng cấp thêm thay vì 0
                'cap_them' => 1, // Đây là cấp thêm
                'doi_lenh' => 0,
                'diem_du_kien' => '',
                'ly_do_cap_them' => $lyDoCapThem,
                'so_luong_cap_them_lit' => $soLuongCapThem,
                'cay_xang_cap_them' => '',
                'nhom_cu_ly' => '',
                'doi_lenh_tuyen' => '',
                'route_hien_thi' => '',
            ]);
        }

        // Trường hợp chỉ có cấp thêm (không có tính toán dầu)
        if (!$dataLuuTinhToan && $dataLuuCapThem) {
            $dataLuuTinhToan = $dataLuuCapThem;
            $dataLuuCapThem = null; // Để tránh lưu 2 lần
        }

        file_put_contents('debug_log.txt', "[POST] Processing action: {$action}.\n", FILE_APPEND);
        if ($action === 'save') {
            // Debug dữ liệu ngay trước khi lưu
            error_log('DEBUG SAVE ACTION: $ketQua exists: ' . ($ketQua ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $ketQuaCapThem exists: ' . ($ketQuaCapThem ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $dataLuuTinhToan exists: ' . ($dataLuuTinhToan ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $dataLuuCapThem exists: ' . ($dataLuuCapThem ? 'YES' : 'NO'));
            if ($dataLuuCapThem) {
                error_log('DEBUG SAVE ACTION: Data to be saved (Cap them): ' . print_r($dataLuuCapThem, true));
            } else {
                error_log('DEBUG SAVE ACTION: $dataLuuCapThem is NULL! $capThem=' . $capThem . ', $soLuongCapThem=' . $soLuongCapThem);
            }

            file_put_contents('debug_log.txt', "[POST] Attempting to save data...\n", FILE_APPEND);

            // Kiểm tra dữ liệu trước khi lưu
            if (!$dataLuuTinhToan) {
                $errorMsg = 'Không có dữ liệu để lưu. $ketQua: ' . ($ketQua ? 'exists' : 'null') . ', $ketQuaCapThem: ' . ($ketQuaCapThem ? 'exists' : 'null');
                error_log('DEBUG SAVE ACTION: ' . $errorMsg);
                throw new Exception($errorMsg);
            }

            $saved = false;

            // Lưu kết quả tính toán dầu (nếu có)
            if ($dataLuuTinhToan) {
                $saved = $luuKetQua->luu($dataLuuTinhToan);
                if (!$saved) {
                    error_log('DEBUG SAVE ACTION: luu() returned false for tinh toan.');
                    throw new Exception('Không thể lưu dữ liệu tính toán. Vui lòng kiểm tra lại.');
                } else {
                    error_log('DEBUG SAVE ACTION: luu() returned true for tinh toan.');
                }
            }

            // Lưu kết quả cấp thêm (nếu có và khác với tính toán dầu)
            if ($saved && $dataLuuCapThem) {
                $savedCapThem = $luuKetQua->luu($dataLuuCapThem);
                if (!$savedCapThem) {
                    error_log('DEBUG SAVE ACTION: luu() returned false for cap them.');
                } else {
                    error_log('DEBUG SAVE ACTION: luu() returned true for cap them.');
                }
            }

            if ($saved) {
                // Điều hướng về trang chính với tàu và số chuyến để hiển thị ngay các đoạn của chuyến
                $redirectSoChuyen = $soChuyen;
                // Giữ nguyên tháng báo cáo người dùng chọn để không bị đổi sau khi lưu
                $qs = 'ten_tau=' . urlencode($tenTau) . '&so_chuyen=' . urlencode((string)$redirectSoChuyen) . '&saved=1';
                if (!empty($thangBaoCao)) { $qs .= '&thang=' . urlencode($thangBaoCao); }

                // Debug redirect URL
                error_log('DEBUG REDIRECT: URL=' . 'index.php?' . $qs);

                header('Location: index.php?' . $qs);
                exit;
            } else {
                throw new Exception('Không thể lưu dữ liệu. Vui lòng thử lại.');
            }
            // Sau khi lưu xong, có thể xóa bản tính tạm
            unset($_SESSION['calc']);
        } else {
            // Lưu vào session và chuyển hướng (PRG) để tránh F5 mất dữ liệu/confirm resubmit
            $_SESSION['calc'] = [
                'form' => $formData,
                'ketQua' => $ketQua,
                'ketQuaCapThem' => $ketQuaCapThem ?? null
            ];
            header('Location: index.php?show=1');
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Nếu là GET và có yêu cầu hiển thị kết quả từ session
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['show']) && isset($_SESSION['calc'])) {
    $ketQua = $_SESSION['calc']['ketQua'] ?? null;
    $ketQuaCapThem = $_SESSION['calc']['ketQuaCapThem'] ?? null;
    $formData = $_SESSION['calc']['form'] ?? $formData;

}

// Xử lý tham số GET khi reload trang (từ onTauChange)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ten_tau'])) {
    // Debug GET parameters on reload
    error_log('DEBUG GET: Parameters: ' . print_r($_GET, true));
    $formData['ten_tau'] = $_GET['ten_tau'];
    if (isset($_GET['so_chuyen']) && !empty($_GET['so_chuyen'])) {
        $formData['so_chuyen'] = $_GET['so_chuyen'];
    } else {
        // Nếu có ten_tau nhưng không có so_chuyen, tự động set mã chuyến cao nhất
        $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($_GET['ten_tau']);
        $formData['so_chuyen'] = $maChuyenCaoNhat > 0 ? $maChuyenCaoNhat : 1;
    }
    // Giữ lại tháng báo cáo nếu được truyền trong URL
    if (isset($_GET['thang']) && preg_match('/^\d{4}-\d{2}$/', $_GET['thang'])) {
        $formData['thang_bao_cao'] = $_GET['thang'];
    }
}

// Tính mã chuyến cao nhất (base) để gán vào data attribute cho client
$maChuyenCaoNhat = 0;
if (!empty($formData['ten_tau'])) {
    $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($formData['ten_tau']);
}

// Tự động set mã chuyến cao nhất CHỈ KHI chưa có số chuyến được chọn
if (!empty($formData['ten_tau']) && (empty($formData['so_chuyen']) || !is_numeric($formData['so_chuyen']))) {
    // Chỉ tự động chọn mã chuyến cao nhất nếu không phải là đang hiển thị kết quả tính toán
    // Vì khi hiển thị kết quả, mã chuyến đã được xác định trong session
    if (!isset($_GET['show'])) {
        $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($formData['ten_tau']);
        $formData['so_chuyen'] = $maChuyenCaoNhat > 0 ? $maChuyenCaoNhat : 1;
    }
}

// Lấy thông tin chuyến hiện tại và các đoạn nếu đã chọn tàu
if (!empty($formData['ten_tau'])) {
    $chuyenHienTai = intval($formData['so_chuyen']);
    $cacDoanCuaChuyen = $luuKetQua->layCacDoanCuaChuyen($formData['ten_tau'], $chuyenHienTai);
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-calculator text-primary me-3"></i>
                    Tính Toán Nhiên Liệu Sử Dụng
                </h1>
                <p class="card-text">
                    Nhập thông tin tàu, tuyến đường và khối lượng hàng hóa để tính toán lượng nhiên liệu cần thiết
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Error Alert -->
<?php if ($error): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Lỗi:</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($saved): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-save me-2"></i>
            Đã lưu kết quả tính toán.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Calculation Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Thông Tin Tính Toán
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateForm()" autocomplete="off">
                    <?php if ($ketQua || (isset($_GET['show']) && isset($_SESSION['calc']))): ?>
                    <input type="hidden" id="has_calc_session" value="1">
                    <?php endif; ?>
                    <!-- Tên tàu -->
                    <div class="mb-3">
                        <label for="ten_tau" class="form-label">
                            <i class="fas fa-ship me-1"></i>
                            Tên tàu <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <select class="form-select" id="ten_tau" name="ten_tau" onchange="onTauChange()">
                                    <option value="">-- Chọn tàu --</option>
                                    <?php $mapPL = $tauPhanLoai->getAll(); foreach ($danhSachTau as $tau): $pl = $mapPL[$tau] ?? 'cong_ty'; ?>
                                    <option value="<?php echo htmlspecialchars($tau); ?>" data-pl="<?php echo htmlspecialchars($pl); ?>" <?php echo ($formData['ten_tau'] === $tau) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tau); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="loc_phan_loai" onchange="filterTauByPhanLoai()">
                                    <option value="">-- Tất cả --</option>
                                    <option value="cong_ty">Sà lan công ty</option>
                                    <option value="thue_ngoai">Thuê ngoài</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Thông tin chuyến -->
                    <div class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="so_chuyen" class="form-label">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Mã chuyến
                                </label>
                                <select class="form-select" id="so_chuyen" name="so_chuyen" onchange="onChuyenChange()" data-preselected="<?php echo htmlspecialchars($formData['so_chuyen']); ?>">
                                    <option value="">Vui lòng chọn tàu</option>
                                </select>
                                <script>
                                    // Đồng bộ hóa giá trị preselected ngay lập tức để tránh race condition
                                    (function() {
                                        const select = document.getElementById('so_chuyen');
                                        const preselectedValue = select.getAttribute('data-preselected');
                                        if (preselectedValue) {
                                            // Kiểm tra xem option đã tồn tại chưa
                                            if (!select.querySelector(`option[value="${preselectedValue}"]`)) {
                                                const option = document.createElement('option');
                                                option.value = preselectedValue;
                                                option.textContent = preselectedValue;
                                                select.appendChild(option);
                                            }
                                            select.value = preselectedValue;
                                        }
                                    })();
                                </script>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-tools me-1"></i>Công cụ chuyến</label>
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="openTripChangeModal()" id="btn_change_trip" title="Di chuyển đoạn sang chuyến khác">
                                        <i class="fas fa-exchange-alt"></i>
                                        <span class="d-none d-xl-inline ms-1">Di chuyển</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="openInsertTripModal()" id="btn_insert_trip" title="Quản lý số chuyến (Thêm/Xóa)">
                                        <i class="fas fa-list-ol"></i>
                                        <span class="d-none d-xl-inline ms-1">QL chuyến</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="openReorderModal()" id="btn_reorder_segments" title="Sắp xếp thứ tự đoạn">
                                        <i class="fas fa-sort"></i>
                                        <span class="d-none d-xl-inline ms-1">Sắp xếp</span>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="thang_bao_cao" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Tháng báo cáo
                                </label>
                                <select class="form-select" id="thang_bao_cao" name="thang_bao_cao">
                                    <?php
                                    // Hiển thị 12 tháng trước đến 2 tháng tới (tổng 15 tháng)
                                    for ($i = -11; $i <= 2; $i++) {
                                        $time = strtotime("$i month");
                                        $value = date('Y-m', $time);
                                        $text = 'Tháng ' . date('m/Y', $time);
                                        // Chọn theo formData nếu có, mặc định tháng hiện tại
                                        $selected = (!empty($formData['thang_bao_cao']) ? ($formData['thang_bao_cao'] === $value) : (date('Y-m') === $value)) ? 'selected' : '';
                                        echo "<option value='{$value}' {$selected}>{$text}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="chuyen_moi" name="chuyen_moi" 
                                           onchange="onToggleChuyenMoi()"
                                           <?php echo ($formData['chuyen_moi'] ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="chuyen_moi">
                                        <strong>Tạo chuyến mới</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Chọn tàu để tải danh sách chuyến. Tick "Tạo chuyến mới" để tạo chuyến tiếp theo. Nhấn "Di chuyển đoạn" để chuyển đoạn giữa các chuyến.
                        </div>
                    </div>


                    <!-- Hiển thị các đoạn của chuyến hiện tại (luôn hiển thị khung) -->
                    <div class="mb-3" id="tripLogDynamic">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Các đoạn của chuyến <?php echo ($chuyenHienTai ?? ''); ?> (sắp xếp theo thứ tự nhập)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php 
                                    // Chỉ truy vấn khi đã có tàu và mã chuyến hợp lệ
                                    $capThemTrongChuyen = [];
                                    if (!empty($formData['ten_tau']) && is_numeric($chuyenHienTai)) {
                                        $capThemTrongChuyen = $luuKetQua->layCapThemCuaChuyen($formData['ten_tau'], (int)$chuyenHienTai);
                                    }
                                    // Gộp các đoạn và cấp thêm để sắp xếp theo thứ tự nhập thực tế (ID)
                                    $combinedRows = [];
                                    foreach ($cacDoanCuaChuyen as $idx => $doan) {
                                        $combinedRows[] = [
                                            'type' => 'doan',
                                            'data' => $doan,
                                            'id' => (int)($doan['___idx'] ?? 0), // Sử dụng ID thực tế từ database
                                            'date' => parse_date_vn($doan['ngay_di'] ?? '') ?: substr((string)($doan['created_at'] ?? ''), 0, 10),
                                            'seq' => $idx
                                        ];
                                    }
                                    foreach ($capThemTrongChuyen as $i => $ct) {
                                        $combinedRows[] = [
                                            'type' => 'cap_them',
                                            'data' => $ct,
                                            'id' => (int)($ct['___idx'] ?? 0), // Sử dụng ID thực tế từ database
                                            'date' => substr((string)($ct['created_at'] ?? ''), 0, 10),
                                            'seq' => 1000 + $i
                                        ];
                                    }
                                    // Sắp xếp theo thứ tự logic: mã chuyến -> thứ tự nhập liệu
                                    usort($combinedRows, function($a, $b){
                                        // Sắp xếp theo mã chuyến (số tăng dần)
                                        $tripA = (int)($a['so_chuyen'] ?? 0);
                                        $tripB = (int)($b['so_chuyen'] ?? 0);
                                        if ($tripA !== $tripB) {
                                            return $tripA <=> $tripB;
                                        }
                                        
                                        // Sắp xếp theo ___idx (thứ tự trong CSV)
                                        $idxA = (int)($a['___idx'] ?? 0);
                                        $idxB = (int)($b['___idx'] ?? 0);
                                        if ($idxA !== $idxB) {
                                            return $idxA <=> $idxB;
                                        }
                                        
                                        // Nếu cùng chuyến và cùng ___idx, sắp xếp theo thứ tự trong mảng
                                        return $a['seq'] <=> $b['seq'];
                                    });
                                ?>
                                <?php if (!empty($capThemTrongChuyen)): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fas fa-gas-pump me-2"></i>
                                    <div>
                                        Đã có <strong><?php echo count($capThemTrongChuyen); ?></strong> lệnh cấp thêm trong chuyến này.
                                        <?php $sumCap = array_sum(array_map(function($r){ return (float)($r['so_luong_cap_them_lit'] ?? 0); }, $capThemTrongChuyen)); ?>
                                        Tổng: <strong><?php echo number_format($sumCap, 0); ?></strong> lít.
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>STT</th>
                                                <th>Điểm đi</th>
                                                <th>Điểm đến</th>
                                                <th>Khối lượng</th>
                                                <th>Nhiên liệu</th>
                                                <th>Ngày đi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="trip_table_body">
                                            <?php if (empty($combinedRows)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-muted">Chưa có dữ liệu cho chuyến này.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php $stt = 1; foreach ($combinedRows as $row): ?>
                                                <?php if ($row['type'] === 'doan'): $doan = $row['data']; ?>
                                                    <?php
                                                        // Ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
                                                        // Tìm key route_hien_thi trong mảng (có thể có vấn đề với CSV parsing)
                                                        $routeHienThi = '';
                                                        foreach ($doan as $key => $value) {
                                                            if (trim($key) === 'route_hien_thi') {
                                                                $routeHienThi = trim((string)$value);
                                                                if ($routeHienThi !== '') {
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Tìm key doi_lenh_tuyen trong mảng
                                                        $doiLenhTuyenJson = '';
                                                        foreach ($doan as $key => $value) {
                                                            if (trim($key) === 'doi_lenh_tuyen') {
                                                                $doiLenhTuyenJson = trim((string)$value);
                                                                if ($doiLenhTuyenJson !== '') {
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        
                                                        $isDoiLenh = (intval($doan['doi_lenh'] ?? 0) === 1);
                                                        
                                                        $routeDisplay = '';
                                                        
                                                        // Bước 1: Ưu tiên dùng route_hien_thi nếu có
                                                        if ($routeHienThi !== '' && strlen($routeHienThi) > 0) {
                                                            $routeDisplay = $routeHienThi;
                                                        }
                                                        // Bước 2: Nếu không có route_hien_thi, thử xây dựng từ doi_lenh_tuyen
                                                        elseif ($doiLenhTuyenJson !== '' && $isDoiLenh) {
                                                            $doiLenhTuyenData = json_decode($doiLenhTuyenJson, true);
                                                            if (is_array($doiLenhTuyenData) && !empty($doiLenhTuyenData)) {
                                                                $routeSegments = [];
                                                                $diemDi = trim((string)($doan['diem_di'] ?? ''));
                                                                if ($diemDi !== '') {
                                                                    $routeSegments[] = $diemDi;
                                                                }
                                                                $diemDuKien = trim((string)($doan['diem_du_kien'] ?? ''));
                                                                if ($diemDuKien !== '') {
                                                                    $routeSegments[] = $diemDuKien;
                                                                }
                                                                foreach ($doiLenhTuyenData as $entry) {
                                                                    if (is_array($entry)) {
                                                                        $label = trim((string)($entry['point'] ?? ''));
                                                                        $suffixParts = [];
                                                                        if (!empty($entry['reason'])) {
                                                                            $suffixParts[] = trim((string)$entry['reason']);
                                                                        }
                                                                        if (!empty($entry['note'])) {
                                                                            $suffixParts[] = trim((string)$entry['note']);
                                                                        }
                                                                        if (!empty($suffixParts)) {
                                                                            $label .= ' (' . implode(' – ', $suffixParts) . ')';
                                                                        }
                                                                        if ($label !== '') {
                                                                            $routeSegments[] = $label;
                                                                        }
                                                                    }
                                                                }
                                                                // Điểm cuối thực tế (diem_den) thường đã có trong doi_lenh_tuyen
                                                                // nhưng để đảm bảo, kiểm tra xem có cần thêm không
                                                                $diemDen = trim((string)($doan['diem_den'] ?? ''));
                                                                if ($diemDen !== '') {
                                                                    $lastSegment = !empty($routeSegments) ? end($routeSegments) : '';
                                                                    $lastPointOnly = preg_replace('/\s*\([^)]*\)\s*$/', '', $lastSegment);
                                                                    // Kiểm tra xem điểm cuối đã có trong routeSegments chưa
                                                                    $found = false;
                                                                    foreach ($routeSegments as $seg) {
                                                                        $segClean = preg_replace('/\s*\([^)]*\)\s*$/', '', $seg);
                                                                        if (stripos($seg, $diemDen) !== false || stripos($segClean, $diemDen) !== false) {
                                                                            $found = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                    if (!$found) {
                                                                        // Nếu điểm cuối chưa có trong route, thêm vào
                                                                        $routeSegments[] = $diemDen;
                                                                    }
                                                                }
                                                                $routeDisplay = implode(' → ', array_filter($routeSegments, function($part){
                                                                    return trim((string)$part) !== '';
                                                                }));
                                                            }
                                                        }
                                                        // Bước 3: Fallback - dùng diem_den nếu không có route_hien_thi và không có doi_lenh_tuyen
                                                        if ($routeDisplay === '') {
                                                            $routeDisplay = trim((string)($doan['diem_den'] ?? ''));
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo $stt++; ?></strong></td>
                                                        <td><?php echo htmlspecialchars($doan['diem_di']); ?></td>
                                                        <td><?php echo htmlspecialchars($routeDisplay); ?></td>
                                                        <td><?php echo htmlspecialchars($doan['khoi_luong_van_chuyen_t']); ?> tấn</td>
                                                        <td><?php echo htmlspecialchars($doan['dau_tinh_toan_lit']); ?> lít</td>
                                                        <td><?php echo htmlspecialchars(format_date_vn($doan['ngay_di'])); ?></td>
                                                    </tr>
                                                <?php else: $ct = $row['data']; ?>
                                                    <tr class="table-warning">
                                                        <td><span class="badge bg-warning text-dark">Cấp thêm</span></td>
                                                        <td colspan="2">
                                                            <span class="text-muted">—</span>
                                                        </td>
                                                        <td><span class="text-muted">—</span></td>
                                                        <td>
                                                            <strong><?php echo number_format((float)($ct['so_luong_cap_them_lit'] ?? 0), 0); ?></strong> lít
                                                            <?php if (!empty($ct['ly_do_cap_them'])): ?>
                                                            <br><small class="text-muted">Lý do: <?php echo htmlspecialchars($ct['ly_do_cap_them']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="text-muted">—</span>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        Đoạn mới sẽ được thêm vào danh sách trên
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Đổi lệnh -->
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="doi_lenh" name="doi_lenh" <?php echo (!empty($formData['doi_lenh']) ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="doi_lenh"><strong>Đổi lệnh trong chuyến</strong></label>
                    </div>

                    <!-- Điểm bắt đầu -->
                    <div class="mb-3">
                        <label for="diem_bat_dau" class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            Điểm bắt đầu <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" class="form-control diem-input" id="diem_bat_dau" name="diem_bat_dau"
                                    value="<?php echo htmlspecialchars($formData['diem_bat_dau']); ?>"
                                    placeholder="Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                    onfocus="showAllDiem(document.getElementById('diem_bat_dau_results'), '');"
                                    oninput="searchDiem(this, document.getElementById('diem_bat_dau_results'))">
                                <div class="dropdown-menu diem-results" id="diem_bat_dau_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="ghi_chu_diem_bat_dau" name="ghi_chu_diem_bat_dau"
                                    placeholder="Ghi chú..." autocomplete="off">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-primary btn-sm me-1" onclick="quickFill('ghi_chu_diem_bat_dau', 'Đổi lệnh')">Đổi lệnh</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="quickFill('ghi_chu_diem_bat_dau', 'Lãnh vật tư')">Lãnh vật tư</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Click vào ô để hiện tất cả điểm có sẵn. Ghi chú sẽ hiển thị: Tên điểm （ghi chú）
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDiem('diem_bat_dau')">
                                <i class="fas fa-undo me-1"></i>Chọn lại
                            </button>
                        </div>
                    </div>

                    <!-- Điểm kết thúc -->
                    <div class="mb-3">
                        <label for="diem_ket_thuc" class="form-label">
                            <i class="fas fa-flag-checkered me-1"></i>
                            Điểm kết thúc dự kiến (B) <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" class="form-control diem-input" id="diem_ket_thuc" name="diem_ket_thuc"
                                    value="<?php echo htmlspecialchars($formData['diem_ket_thuc']); ?>"
                                    placeholder="Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                    onfocus="showAllDiem(document.getElementById('diem_ket_thuc_results'), document.getElementById('diem_bat_dau').value);"
                                    oninput="searchDiem(this, document.getElementById('diem_ket_thuc_results'))">
                                <div class="dropdown-menu diem-results" id="diem_ket_thuc_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="ghi_chu_diem_ket_thuc" name="ghi_chu_diem_ket_thuc"
                                    placeholder="Ghi chú..." autocomplete="off">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-primary btn-sm me-1" onclick="quickFill('ghi_chu_diem_ket_thuc', 'Đổi lệnh')">Đổi lệnh</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="quickFill('ghi_chu_diem_ket_thuc', 'Lãnh vật tư')">Lãnh vật tư</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Nếu bật Đổi lệnh, đây là điểm B (đổi lệnh tại đây)
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDiem('diem_ket_thuc')">
                                <i class="fas fa-undo me-1"></i>Chọn lại
                            </button>
                        </div>
                    </div>

                    <!-- Khoảng cách cho tuyến A → B (hiển thị khi chọn đủ 2 điểm và không đổi lệnh) -->
                    <div id="khoang_cach_thu_cong_fields" class="mb-3" style="display: none;">
                        <label for="khoang_cach_thu_cong" class="form-label">
                            <i class="fas fa-ruler-combined me-1"></i>
                            Khoảng cách (A → B) - Km <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0.1" class="form-control" id="khoang_cach_thu_cong" name="khoang_cach_thu_cong"
                                   value="" autocomplete="off" placeholder="Nhập khoảng cách...">
                            <button class="btn btn-outline-secondary" type="button" id="btn_unlock_khoang_cach" style="display: none;"
                                    onclick="unlockKhoangCach()" title="Cho phép chỉnh sửa">
                                <i class="fas fa-lock-open"></i> Sửa
                            </button>
                        </div>
                        <div class="form-text" id="khoang_cach_help_text">
                            <i class="fas fa-info-circle me-1"></i>
                            <span id="khoang_cach_status">Đang kiểm tra...</span>
                        </div>
                    </div>

                    <!-- Khu vực đổi lệnh: điểm đến mới C + khoảng cách thực tế -->
                    <div id="doi_lenh_fields" class="border rounded p-3 mb-3" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-location-arrow me-1"></i>
                                Điểm đến mới (C, D, ...) <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" id="prefilled_diem_moi_json" value="<?php echo htmlspecialchars(json_encode($formData['diem_moi_list'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="prefilled_diem_moi_json" value="<?php echo htmlspecialchars(json_encode($formData['diem_moi_list'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                            <div id="ds_diem_moi_wrapper">
                                <div class="row g-2 mb-2 diem-moi-item">
                                    <div class="col-lg-5 col-md-6">
                                        <div class="position-relative">
                                            <input type="text" class="form-control diem-input" name="diem_moi[]"
                                                placeholder="Điểm C - Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                                onfocus="showAllDiem(this.nextElementSibling, '');"
                                                oninput="searchDiem(this, this.nextElementSibling)">
                                            <div class="dropdown-menu diem-results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-5">
                                        <div class="reason-group">
                                            <input type="text" class="form-control form-control-sm diem-moi-reason" name="diem_moi_reason[]"
                                                placeholder="Lý do thêm..." autocomplete="off">
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-primary btn-sm me-1" onclick="this.parentElement.previousElementSibling.value='Đổi lệnh'">Đổi lệnh</button>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="this.parentElement.previousElementSibling.value='Lãnh vật tư'">Lãnh vật tư</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-lg-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="xoaDiemMoi(this)"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="themDiemMoi()"><i class="fas fa-plus me-1"></i>Thêm điểm</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetDiemDangChon()" title="Chọn lại điểm đang chỉnh"><i class="fas fa-undo me-1"></i>Chọn lại</button>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Có thể thêm nhiều điểm (C, D, E, ...). Hệ thống sẽ dùng <strong>Điểm cuối</strong> cho tính toán và <strong>Km thực tế</strong> bạn nhập là tổng cho toàn hành trình.
                            </div>
                            <div class="mt-3">
                                <label for="ghi_chu_diem_moi" class="form-label">Ghi chú cho điểm cuối (tùy chọn)</label>
                                <input type="text" class="form-control" id="ghi_chu_diem_moi" name="ghi_chu_diem_moi"
                                    placeholder="Ghi chú..." autocomplete="off">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-primary btn-sm me-1" onclick="quickFill('ghi_chu_diem_moi', 'Đổi lệnh')">Đổi lệnh</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="quickFill('ghi_chu_diem_moi', 'Lãnh vật tư')">Lãnh vật tư</button>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="khoang_cach_thuc_te" class="form-label">
                                <i class="fas fa-ruler-combined me-1"></i>
                                Tổng khoảng cách thực tế (A → B (đổi lệnh) → C) - Km <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.1" min="0.1" class="form-control" id="khoang_cach_thuc_te" name="khoang_cach_thuc_te"
                                   value="<?php echo htmlspecialchars($formData['khoang_cach_thuc_te'] ?? ''); ?>" autocomplete="off">
                            <div class="form-text">Nhập tổng Km thực tế của cả hành trình.</div>
                        </div>
                    </div>

                    <!-- Khối lượng -->
                    <div class="mb-3">
                        <label for="khoi_luong" class="form-label">
                            <i class="fas fa-weight-hanging me-1"></i>
                            Khối lượng hàng hóa (tấn) <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="khoi_luong" name="khoi_luong" 
                               value="<?php echo htmlspecialchars($formData['khoi_luong']); ?>" 
                               min="0" step="0.01" autocomplete="off"
                               data-bs-toggle="tooltip" data-bs-placement="top" 
                               title="Nhập 0 nếu tàu chạy không hàng">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Nhập 0 nếu tàu chạy không hàng, nhập khối lượng thực tế nếu có hàng
                        </div>
                    </div>

                    <!-- Ngày đi - đến - dỡ xong -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label id="label_ngay_di" for="ngay_di" class="form-label"><i class="fas fa-calendar-day me-1"></i><span class="label-text">Ngày đi</span></label>
                            <input type="text" class="form-control vn-date" id="ngay_di" name="ngay_di" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_di'])); ?>" readonly>
                            <div class="form-text" id="ngay_di_help" style="display: none;">
                                <i class="fas fa-info-circle text-info me-1"></i>
                                Ngày sẽ tự động lấy từ chuyến trước đó
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ngay_den" class="form-label"><i class="fas fa-calendar-check me-1"></i>Ngày đến</label>
                            <input type="text" class="form-control vn-date" id="ngay_den" name="ngay_den" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_den'])); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ngay_do_xong" class="form-label"><i class="fas fa-box-open me-1"></i>Ngày dỡ xong</label>
                            <input type="text" class="form-control vn-date" id="ngay_do_xong" name="ngay_do_xong" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_do_xong'])); ?>">
                        </div>
                    </div>

                    <!-- Loại hàng -->
                    <div class="mb-3">
                        <label for="loai_hang" class="form-label"><i class="fas fa-tags me-1"></i>Loại hàng</label>
                        <select class="form-select" id="loai_hang" name="loai_hang">
                            <option value="">-- Chọn loại hàng --</option>
                            <?php foreach ($danhSachLoaiHang as $lh): $val = (string)($lh['ten_loai_hang'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($formData['loai_hang'] === $val ? 'selected' : ''); ?>><?php echo htmlspecialchars($val); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Quản lý danh mục tại: <a href="admin/quan_ly_loai_hang.php">Quản lý loại hàng</a></div>
                    </div>

                    <!-- Ghi chú -->
                    <div class="mb-3">
                        <label for="ghi_chu" class="form-label"><i class="fas fa-sticky-note me-1"></i>Ghi chú</label>
                        <input type="text" class="form-control" id="ghi_chu" name="ghi_chu" value="<?php echo htmlspecialchars($formData['ghi_chu']); ?>" autocomplete="off" placeholder="Nhập ghi chú (không phải ngày tạo)">
                        <div class="mt-1">
                            <button type="button" class="btn btn-primary btn-sm me-1" onclick="quickFill('ghi_chu', 'Đổi lệnh')">Đổi lệnh</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="quickFill('ghi_chu', 'Lãnh vật tư')">Lãnh vật tư</button>
                        </div>
                    </div>

                    <!-- Nút gạt hiện/ẩn form cấp dầu -->
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle_cap_them" onchange="toggleCapThemForm(this.checked)">
                        <label class="form-check-label" for="toggle_cap_them">
                            <i class="fas fa-gas-pump me-1"></i>
                            <strong>Cấp thêm</strong>
                        </label>
                    </div>

                    <!-- Form cấp thêm - ẩn mặc định -->
                    <div class="card border-primary mb-3" id="cap_them_card" style="display: none;">
                        <div class="card-header bg-primary text-white py-2">
                            <i class="fas fa-gas-pump me-2"></i>
                            <strong>Cấp thêm (tùy chọn)</strong>
                        </div>
                        <div class="card-body">
                            <input type="hidden" id="cap_them" name="cap_them" value="<?php echo $formData['cap_them'] ?? 0; ?>">
                        <div class="mb-3">
                            <label class="form-label">Loại <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_bom_nuoc" value="bom_nuoc" <?php echo ($formData['loai_cap_them'] === 'bom_nuoc') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_bom_nuoc">Ma nơ</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_qua_cau" value="qua_cau" <?php echo ($formData['loai_cap_them'] === 'qua_cau') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_qua_cau">Qua cầu</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_ro_dai_ve_sinh" value="ro_dai_ve_sinh" <?php echo ($formData['loai_cap_them'] === 'ro_dai_ve_sinh') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_ro_dai_ve_sinh">Rô đai+ vệ sinh</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_khac" value="khac" <?php echo ($formData['loai_cap_them'] === 'khac') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_khac">Khác</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="dia_diem_cap_them_wrapper">
                            <label for="dia_diem_cap_them" class="form-label">Địa điểm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control diem-input" id="dia_diem_cap_them" name="dia_diem_cap_them"
                                value="<?php echo htmlspecialchars($formData['dia_diem_cap_them']); ?>"
                                placeholder="Nhập địa điểm (hoặc chọn từ gợi ý)..." autocomplete="off"
                                onfocus="showAllDiem(document.getElementById('dia_diem_cap_them_results'), '');"
                                oninput="searchDiem(this, document.getElementById('dia_diem_cap_them_results'))">
                            <div class="dropdown-menu diem-results" id="dia_diem_cap_them_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Có thể nhập tự do hoặc chọn từ danh sách gợi ý
                            </div>
                        </div>
                        <!-- Ô nhập lý do (chỉ hiện khi chọn "Khác") -->
                        <div class="mb-3" id="ly_do_cap_them_wrapper" style="display:none;">
                            <label for="ly_do_cap_them_khac" class="form-label">Lý do tiêu hao <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ly_do_cap_them_khac" name="ly_do_cap_them_khac" autocomplete="off" placeholder="Nhập lý do tiêu hao..." value="<?php echo htmlspecialchars($formData['ly_do_cap_them_khac']); ?>">
                            <div class="mt-1">
                                <button type="button" class="btn btn-primary btn-sm me-1" onclick="quickFill('ly_do_cap_them_khac', 'Đổi lệnh')">Đổi lệnh</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="quickFill('ly_do_cap_them_khac', 'Lãnh vật tư')">Lãnh vật tư</button>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Nhập lý do tiêu hao dầu (ví dụ: dầu cho thiết bị, dầu khác...)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="so_luong_cap_them" class="form-label">Số lượng (Lít) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="so_luong_cap_them" name="so_luong_cap_them" value="<?php echo htmlspecialchars($formData['so_luong_cap_them']); ?>" min="0.01" step="0.01" autocomplete="off">
                        </div>
                        <!-- Ô preview (luôn hiển thị, readonly) -->
                        <div class="mb-3">
                            <label for="ly_do_cap_them_display" class="form-label" id="ly_do_cap_them_label">Lý do tiêu hao (tự động tạo)</label>
                            <input type="text" class="form-control" id="ly_do_cap_them_display" readonly
                                   value="" style="background-color: #f8f9fa; cursor: not-allowed;">
                            <div class="form-text" id="ly_do_cap_them_help">
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="ly_do_cap_them_help_text">Lý do sẽ được tự động tạo dựa trên loại và địa điểm bạn chọn</span>
                            </div>
                        </div>
                        <div class="mb-3" id="ngay_cap_them_group" style="display: none;">
                            <label for="ngay_cap_them" class="form-label">Ngày cấp thêm</label>
                            <input type="text" class="form-control vn-date" id="ngay_cap_them" placeholder="dd/mm/yyyy"
                                   value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_di'])); ?>" autocomplete="off">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <span class="ngay-cap-hint">Chọn ngày cấp thêm thực tế (tùy chọn cho Dầu ma nơ)</span>
                            </div>
                        </div>
                        <div class="alert alert-success" id="cap_them_preview">
                            <i class="fas fa-eye me-2"></i>
                            <strong>Kết quả sẽ lưu:</strong><br>
                            <div class="mt-2">
                                <strong>Lý do (sẽ lưu vào hệ thống):</strong><br>
                                <span id="cap_them_result_text" class="fw-bold">Dầu ma nơ tại bến [Địa điểm] 01 chuyến x [Số lượng] lít</span>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Trong báo cáo Excel sẽ hiển thị: <strong>CẤP THÊM: [Lý do trên]</strong>
                            </div>
                        </div>
                        </div><!-- end card-body -->
                    </div><!-- end card (Cấp thêm) -->
                    <div id="cap_them_fields" style="display:none;"></div><!-- placeholder để giữ tương thích -->

                    <script>
                    // Hàm điền nhanh nội dung vào ô input
                    function quickFill(inputId, value) {
                        const input = document.getElementById(inputId);
                        if (input) {
                            input.value = value;
                            input.focus();
                            // Trigger sự kiện input để cập nhật preview nếu cần
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }

                    // Hàm toggle hiện/ẩn form cấp dầu
                    function toggleCapThemForm(show) {
                        const card = document.getElementById('cap_them_card');
                        const capThemHidden = document.getElementById('cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const lyDoDisplayInput = document.getElementById('ly_do_cap_them_display');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (show) {
                            card.style.display = 'block';
                            // Set cap_them = 1 khi hiện form (user đã bật toggle)
                            if (capThemHidden) capThemHidden.value = '1';

                            // Đảm bảo các inputs KHÔNG bị disabled và có thể validate
                            if (diaDiemInput) {
                                diaDiemInput.disabled = false;
                                diaDiemInput.removeAttribute('disabled');
                            }
                            if (soLuongInput) {
                                soLuongInput.disabled = false;
                                soLuongInput.removeAttribute('disabled');
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.disabled = false;
                                lyDoDisplayInput.removeAttribute('disabled');
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.disabled = false;
                                lyDoKhacInput.removeAttribute('disabled');
                            }

                            // Trigger change event để hiển thị đúng form fields dựa trên loại được chọn
                            setTimeout(function() {
                                const selectedRadio = document.querySelector('input[name="loai_cap_them"]:checked');
                                if (selectedRadio) {
                                    const changeEvent = new Event('change', { bubbles: true });
                                    selectedRadio.dispatchEvent(changeEvent);
                                }
                            }, 150);
                        } else {
                            card.style.display = 'none';
                            // Reset giá trị khi ẩn (cap_them = 0) và DISABLE inputs để tránh validation
                            if (capThemHidden) capThemHidden.value = '0';

                            // Disable và reset value để tránh lỗi "invalid form control is not focusable"
                            if (soLuongInput) {
                                soLuongInput.value = '';  // Xóa value để không vi phạm min="0.01"
                                soLuongInput.disabled = true;  // Disable để skip validation
                            }
                            if (diaDiemInput) {
                                diaDiemInput.value = '';
                                diaDiemInput.disabled = true;
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.value = '';
                                lyDoDisplayInput.disabled = true;
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.value = '';
                                lyDoKhacInput.disabled = true;
                            }
                        }
                    }

                    // Toggle hiển thị địa điểm hoặc lý do tùy theo loại
                    document.querySelectorAll('input[name="loai_cap_them"]').forEach(function(radio) {
                        radio.addEventListener('change', function() {
                            console.log('Radio changed to:', this.value); // DEBUG

                            const diaDiemWrapper = document.getElementById('dia_diem_cap_them_wrapper');
                            const lyDoWrapper = document.getElementById('ly_do_cap_them_wrapper');
                            const diaDiemInput = document.getElementById('dia_diem_cap_them');
                            const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');
                            const lyDoHelpText = document.getElementById('ly_do_cap_them_help_text');
                            const ngayCapInput = document.getElementById('ngay_cap_them');
                            const ngayCapRequired = document.querySelector('.ngay-cap-required');
                            const ngayCapHint = document.querySelector('.ngay-cap-hint');

                            if (this.value === 'khac') {
                                console.log('Showing ly do input, hiding dia diem'); // DEBUG

                                // Ẩn địa điểm, hiện lý do nhập
                                if (diaDiemWrapper) diaDiemWrapper.style.display = 'none';
                                if (lyDoWrapper) {
                                    lyDoWrapper.style.display = 'block';
                                    console.log('lyDoWrapper display set to block'); // DEBUG
                                }

                                // Bỏ required cho địa điểm, thêm required cho lý do
                                if (diaDiemInput) diaDiemInput.removeAttribute('required');
                                if (lyDoKhacInput) {
                                    lyDoKhacInput.setAttribute('required', 'required');
                                    lyDoKhacInput.disabled = false;
                                    lyDoKhacInput.removeAttribute('disabled');
                                }

                                // Đổi help text
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tính dựa trên lý do bạn nhập và số lượng';
                                }

                                // Ngày cấp không bắt buộc
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';
                            } else if (this.value === 'bom_nuoc') {
                                // Hiện địa điểm, ẩn lý do nhập
                                diaDiemWrapper.style.display = 'block';
                                lyDoWrapper.style.display = 'none';

                                // Không yêu cầu địa điểm cho Rô đai+ vệ sinh
                                if (diaDiemInput) {
                                    diaDiemInput.value = '';
                                    diaDiemInput.removeAttribute('required');
                                    diaDiemInput.disabled = true;
                                }
                                if (lyDoKhacInput) {
                                    lyDoKhacInput.removeAttribute('required');
                                }
                            } else if (this.value === 'ro_dai_ve_sinh') {
                                // Rô đai+ vệ sinh: KHÔNG cần địa điểm, ẩn ô địa điểm và ô lý do nhập
                                if (diaDiemWrapper) diaDiemWrapper.style.display = 'none';
                                if (lyDoWrapper) lyDoWrapper.style.display = 'none';

                                // Bỏ required cho địa điểm và lý do tự nhập
                                if (diaDiemInput) diaDiemInput.removeAttribute('required');
                                if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                                // Help text
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tạo theo mẫu: Dầu rô đai+ vệ sinh 01 máy chính';
                                }

                                // Ngày cấp không bắt buộc
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';

                                // Không yêu cầu địa điểm cho Rô đai+ vệ sinh
                                if (diaDiemInput) {
                                    diaDiemInput.value = '';
                                    diaDiemInput.removeAttribute('required');
                                }
                                if (lyDoKhacInput) {
                                    lyDoKhacInput.removeAttribute('required');
                                }
                            } else {
                                // Qua cầu: Hiện địa điểm, ẩn lý do nhập
                                diaDiemWrapper.style.display = 'block';
                                lyDoWrapper.style.display = 'none';

                                // Thêm required cho địa điểm, bỏ required cho lý do
                                if (diaDiemInput) {
                                    diaDiemInput.setAttribute('required', 'required');
                                    diaDiemInput.disabled = false;
                                    diaDiemInput.removeAttribute('disabled');
                                }
                                if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                                // Đổi help text về mặc định
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tạo dựa trên loại và địa điểm bạn chọn';
                                }

                                // Ngày cấp không bắt buộc
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';
                            }

                            // Cập nhật preview
                            updateCapThemPreview();
                        });
                    });
                    
                    // Khởi tạo trạng thái ban đầu cho ngày cấp (Dầu ma nơ được chọn mặc định)
                    document.addEventListener('DOMContentLoaded', function() {
                        const bomNuocRadio = document.getElementById('loai_bom_nuoc');
                        if (bomNuocRadio && bomNuocRadio.checked) {
                            const ngayCapInput = document.getElementById('ngay_cap_them');
                            const ngayCapRequired = document.querySelector('.ngay-cap-required');
                            const ngayCapHint = document.querySelector('.ngay-cap-hint');
                            if (ngayCapInput) ngayCapInput.required = false;
                            if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                            if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn cho Dầu ma nơ)';
                        }

                        // Đảm bảo tất cả inputs trong form cấp thêm KHÔNG bị disabled khi trang load
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (diaDiemInput) {
                            diaDiemInput.disabled = false;
                            diaDiemInput.removeAttribute('disabled');
                        }
                        if (soLuongInput) {
                            soLuongInput.disabled = false;
                            soLuongInput.removeAttribute('disabled');
                        }
                        if (lyDoKhacInput) {
                            lyDoKhacInput.disabled = false;
                            lyDoKhacInput.removeAttribute('disabled');
                        }
                    });

                    // Hàm cập nhật preview cấp thêm
                    function updateCapThemPreview() {
                        // Đảm bảo tất cả inputs LUÔN enabled (không bị disable bởi bất kỳ logic nào)
                        const diaDiemInputEl = document.getElementById('dia_diem_cap_them');
                        const lyDoKhacInputEl = document.getElementById('ly_do_cap_them_khac');
                        const lyDoDisplayInputEl = document.getElementById('ly_do_cap_them_display');
                        const soLuongInputEl = document.getElementById('so_luong_cap_them');

                        if (diaDiemInputEl) {
                            diaDiemInputEl.disabled = false;
                            diaDiemInputEl.removeAttribute('disabled');
                        }
                        if (lyDoKhacInputEl) {
                            lyDoKhacInputEl.disabled = false;
                            lyDoKhacInputEl.removeAttribute('disabled');
                        }
                        if (soLuongInputEl) {
                            soLuongInputEl.disabled = false;
                            soLuongInputEl.removeAttribute('disabled');
                        }

                        const loai = document.querySelector('input[name="loai_cap_them"]:checked')?.value || 'bom_nuoc';
                        const diaDiem = diaDiemInputEl?.value.trim() || '';
                        const lyDoKhac = lyDoKhacInputEl?.value.trim() || '';
                        const soLuong = soLuongInputEl?.value || '';
                        const resultTextEl = document.getElementById('cap_them_result_text');

                        let resultText = '';
                        let displayText = '';

                        if (loai === 'khac') {
                            // Tạo text từ lý do người nhập + số lượng
                            if (lyDoKhac) {
                                displayText = soLuong ? `${lyDoKhac} x ${soLuong} lít` : lyDoKhac;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: [Lý do]`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        } else if (loai === 'bom_nuoc') {
                            if (diaDiem) {
                                const lyDoBase = `Dầu ma nơ tại bến ${diaDiem} 01 chuyến`;
                                displayText = soLuong ? `${lyDoBase} x ${soLuong} lít` : lyDoBase;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: Dầu ma nơ tại bến [Địa điểm] 01 chuyến`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        } else if (loai === 'ro_dai_ve_sinh') {
                            const lyDoBase = 'Dầu rô đai+ vệ sinh 01 máy chính';
                            displayText = soLuong ? `${lyDoBase} x ${soLuong} lít` : lyDoBase;
                            resultText = `CẤP THÊM: ${displayText}`;
                            
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        } else {
                            // Qua cầu
                            if (diaDiem) {
                                const lyDoBase = `Dầu bơm nước qua cầu ${diaDiem} 01 chuyến`;
                                displayText = soLuong ? `${lyDoBase} x ${soLuong} lít` : lyDoBase;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: Dầu bơm nước qua cầu [Địa điểm] 01 chuyến`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        }

                        if (resultTextEl) {
                            resultTextEl.textContent = resultText.replace('CẤP THÊM: ', '');
                        }
                    }

                    // Lắng nghe thay đổi trên các input
                    const diaDiemInput = document.getElementById('dia_diem_cap_them');
                    const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');
                    const soLuongInput = document.getElementById('so_luong_cap_them');
                    const ngayCapInput = document.getElementById('ngay_cap_them');
                    const hiddenNgayDiInput = document.getElementById('ngay_di');
                    const syncNgayCapValue = () => {
                        if (hiddenNgayDiInput) {
                            hiddenNgayDiInput.value = ngayCapInput ? ngayCapInput.value : '';
                        }
                    };

                    if (diaDiemInput) {
                        diaDiemInput.addEventListener('input', updateCapThemPreview);
                        diaDiemInput.addEventListener('change', updateCapThemPreview);
                        diaDiemInput.addEventListener('blur', updateCapThemPreview);
                        // Kiểm tra thay đổi mỗi 500ms để bắt được việc chọn từ dropdown
                        setInterval(function() {
                            if (diaDiemInput.value !== diaDiemInput.dataset.lastValue) {
                                diaDiemInput.dataset.lastValue = diaDiemInput.value;
                                updateCapThemPreview();
                            }
                        }, 500);
                    }

                    if (lyDoKhacInput) {
                        lyDoKhacInput.addEventListener('input', updateCapThemPreview);
                        lyDoKhacInput.addEventListener('change', updateCapThemPreview);
                    }

                    // Auto-enable cấp thêm khi nhập số lượng
                    if (soLuongInput) {
                        soLuongInput.addEventListener('input', function() {
                            updateCapThemPreview();
                            // Auto-enable toggle và cap_them khi có nhập số lượng > 0
                            const hasQuantity = parseFloat(soLuongInput.value) > 0;
                            const toggleCheckbox = document.getElementById('toggle_cap_them');
                            const capThemHidden = document.getElementById('cap_them');

                            if (hasQuantity) {
                                // Auto-check toggle và set cap_them = 1
                                if (toggleCheckbox && !toggleCheckbox.checked) {
                                    toggleCheckbox.checked = true;
                                    toggleCapThemForm(true);
                                }
                                if (capThemHidden) {
                                    capThemHidden.value = '1';
                                }
                            }
                        });
                    }

                    if (ngayCapInput) {
                        if (hiddenNgayDiInput && !ngayCapInput.value && hiddenNgayDiInput.value) {
                            ngayCapInput.value = hiddenNgayDiInput.value;
                        }
                        ngayCapInput.addEventListener('input', () => {
                            syncNgayCapValue();
                        });
                        ngayCapInput.addEventListener('change', () => {
                            syncNgayCapValue();
                        });
                    }

                    // Cập nhật preview khi load trang
                    document.addEventListener('DOMContentLoaded', function() {
                        // Fix #2,#6,#8,#10: Bỏ required vì cấp thêm là tùy chọn
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (diaDiemInput) diaDiemInput.removeAttribute('required');
                        if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                        // FIX: Disable inputs ngay khi page load nếu form đang ẩn (để tránh validation error)
                        // NHƯNG chỉ xóa value nếu KHÔNG có dữ liệu cấp thêm (để tránh mất dữ liệu khi tính toán)
                        const card = document.getElementById('cap_them_card');
                        const toggleCheckbox = document.getElementById('toggle_cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const capThemHidden = document.getElementById('cap_them');

                        // Kiểm tra xem form có đang ẩn không
                        const isFormHidden = !toggleCheckbox || !toggleCheckbox.checked;

                        // Kiểm tra xem có dữ liệu cấp thêm từ PHP không (từ session sau tính toán)
                        const capThemValue = capThemHidden ? capThemHidden.value : '0';
                        const soLuongValue = soLuongInput ? soLuongInput.value : '';
                        const hasCapThemData = capThemValue == '1' || (soLuongValue && parseFloat(soLuongValue) > 0);

                        // CHỈ disable và xóa value nếu form ẩn VÀ KHÔNG có dữ liệu
                        if (card && isFormHidden && !hasCapThemData) {
                            const lyDoDisplayInput = document.getElementById('ly_do_cap_them_display');

                            // Disable tất cả inputs trong form cấp thêm
                            if (diaDiemInput) {
                                diaDiemInput.disabled = true;
                                diaDiemInput.value = '';
                            }
                            if (soLuongInput) {
                                soLuongInput.disabled = true;
                                soLuongInput.value = '';  // Xóa value để tránh vi phạm min="0.01"
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.disabled = true;
                                lyDoKhacInput.value = '';
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.disabled = true;
                                lyDoDisplayInput.value = '';
                            }
                        }

                        // QUAN TRỌNG: Trigger change event để hiển thị đúng form dựa trên radio được chọn
                        const selectedRadio = document.querySelector('input[name="loai_cap_them"]:checked');
                        if (selectedRadio) {
                            // Tạo và dispatch change event
                            const changeEvent = new Event('change', { bubbles: true });
                            selectedRadio.dispatchEvent(changeEvent);
                        }

                        updateCapThemPreview();

                        // Auto-show form cấp thêm nếu có dữ liệu (reuse variables đã khai báo ở trên)
                        if (capThemValue == '1' || (soLuongValue && parseFloat(soLuongValue) > 0)) {
                            if (toggleCheckbox) {
                                toggleCheckbox.checked = true;
                                toggleCapThemForm(true);

                                // Trigger change event cho radio loai_cap_them để hiển thị đúng UI
                                setTimeout(function() {
                                    const selectedLoaiCapThem = document.querySelector('input[name="loai_cap_them"]:checked');
                                    if (selectedLoaiCapThem) {
                                        const changeEvent = new Event('change', { bubbles: true });
                                        selectedLoaiCapThem.dispatchEvent(changeEvent);
                                    }
                                    updateCapThemPreview();
                                }, 200);
                            }
                        }

                        // Watchdog: Đảm bảo inputs trong form cấp thêm LUÔN enabled (check mỗi 300ms)
                        setInterval(function() {
                            const card = document.getElementById('cap_them_card');
                            // Chỉ chạy watchdog khi form đang hiển thị
                            if (card && card.style.display !== 'none') {
                                const diaDiem = document.getElementById('dia_diem_cap_them');
                                const soLuong = document.getElementById('so_luong_cap_them');
                                const lyDoKhac = document.getElementById('ly_do_cap_them_khac');

                                if (diaDiem && diaDiem.disabled) {
                                    diaDiem.disabled = false;
                                    diaDiem.removeAttribute('disabled');
                                }
                                if (soLuong && soLuong.disabled) {
                                    soLuong.disabled = false;
                                    soLuong.removeAttribute('disabled');
                                }
                                if (lyDoKhac && lyDoKhac.disabled) {
                                    lyDoKhac.disabled = false;
                                    lyDoKhac.removeAttribute('disabled');
                                }
                            }
                        }, 300);
                    });
                    </script>

                    <!-- Actions -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <button type="submit" name="action" value="calculate" class="btn btn-primary btn-lg">
                            <i class="fas fa-calculator me-2"></i>
                            Tính Toán Nhiên Liệu
                        </button>
                        <button type="submit" name="action" value="save" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Lưu Kết Quả
                        </button>
                        <a href="lich_su.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-database me-2"></i>Xem lịch sử
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Information Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông Tin Hướng Dẫn
                </h5>
            </div>
            <div class="card-body">
                <h6><i class="fas fa-formula me-2"></i>Công thức tính:</h6>
                <p class="small">
                    <strong>Q = [(Sch+Skh)×Kkh] + (Sch×D×Kch)</strong>
                </p>
                
                <h6><i class="fas fa-list me-2"></i>Trong đó:</h6>
                <ul class="small">
                    <li><strong>Q:</strong> Nhiên liệu tiêu thụ (Lít)</li>
                    <li><strong>Sch:</strong> Quãng đường có hàng (Km)</li>
                    <li><strong>Skh:</strong> Quãng đường không hàng (Km)</li>
                    <li><strong>Kkh:</strong> Hệ số không hàng (Lít/Km)</li>
                    <li><strong>Kch:</strong> Hệ số có hàng (Lít/T.Km)</li>
                    <li><strong>D:</strong> Khối lượng hàng hóa (Tấn)</li>
                </ul>

                <h6><i class="fas fa-lightbulb me-2"></i>Lưu ý:</h6>
                <ul class="small">
                    <li>Nếu khối lượng = 0: Tính quãng đường không hàng</li>
                    <li>Nếu khối lượng > 0: Tính quãng đường có hàng</li>
                    <li>Hệ số nhiên liệu phụ thuộc vào loại tàu và khoảng cách</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Results Section -->
<?php if ($ketQua): ?>
<div class="row mt-4" id="ket-qua-tinh-toan">
    <div class="col-12">
        <div class="card result-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Kết Quả Tính Toán
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($ketQuaCapThem) && $ketQuaCapThem && $ketQua['loai_tinh'] !== 'cap_them'): ?>
                <!-- Thông báo khi có cả hai kết quả -->
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Lưu ý:</strong> Khi lưu, hệ thống sẽ lưu <strong>2 bản ghi riêng biệt</strong>:
                    <ul class="mb-0 mt-2">
                        <li><strong>Bản ghi 1:</strong> Tính toán dầu cho quảng đường (<strong><?php echo number_format($ketQua['nhien_lieu_lit'], 0); ?> lít</strong>)</li>
                        <li><strong>Bản ghi 2:</strong> Cấp thêm dầu (<strong><?php echo number_format($ketQuaCapThem['nhien_lieu_lit'], 0); ?> lít</strong> - <?php echo htmlspecialchars($ketQuaCapThem['chi_tiet']['cong_thuc'] ?? ''); ?>)</li>
                    </ul>
                </div>
                <?php endif; ?>
                <div class="row">
                    <!-- Thông tin cơ bản -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-ship me-2"></i>Thông tin chuyến đi:</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Tàu:</strong></td>
                                <td><?php echo htmlspecialchars($ketQua['thong_tin']['ten_tau']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tuyến đường:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($ketQua['thong_tin']['route_hien_thi'] ?? ($ketQua['thong_tin']['diem_bat_dau'] . ' → ' . $ketQua['thong_tin']['diem_ket_thuc'])); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khoảng cách:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?php echo number_format($ketQua['thong_tin']['khoang_cach_km'], 1); ?> km
                                        <?php if (isset($ketQua['thong_tin']['khoang_cach_thu_cong']) && $ketQua['thong_tin']['khoang_cach_thu_cong']): ?>
                                            <span class="badge bg-warning text-dark ms-2">Thủ công</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Nhóm cự ly:</strong></td>
                                <td>
                                    <?php $nhomLabel = $ketQua['thong_tin']['nhom_cu_ly_label'] ?? ''; ?>
                                    <?php if ($nhomLabel): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($nhomLabel); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khối lượng:</strong></td>
                                <td><?php echo number_format($ketQua['thong_tin']['khoi_luong_tan'], 2); ?> tấn</td>
                            </tr>
                            <tr>
                                <td><strong>Loại tính:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="badge bg-info">Cấp thêm</span>
                                    <?php elseif ($ketQua['loai_tinh'] === 'khong_hang'): ?>
                                        <span class="badge bg-warning">Không hàng</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Có hàng</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kết quả tính toán -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-gas-pump me-2"></i>Kết quả nhiên liệu:</h6>
                        <div class="text-center">
                            <div class="display-4 text-success fw-bold">
                                <?php echo number_format($ketQua['nhien_lieu_lit'], 0); ?>
                            </div>
                            <div class="h5 text-muted">Lít</div>
                        </div>
                        
                        <hr>
                        
                        <?php if ($ketQua['loai_tinh'] !== 'cap_them'): ?>
                        <h6><i class="fas fa-cogs me-2"></i>Hệ số sử dụng:</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Hệ số không hàng (Kkh):</td>
                                <td class="text-end"><?php echo number_format($ketQua['thong_tin']['he_so_ko_hang'], 6); ?> Lít/Km</td>
                            </tr>
                            <tr>
                                <td>Hệ số có hàng (Kch):</td>
                                <td class="text-end"><?php echo number_format($ketQua['thong_tin']['he_so_co_hang'], 7); ?> Lít/T.Km</td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chi tiết công thức -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert <?php echo ($ketQua['loai_tinh'] === 'cap_them') ? 'alert-warning' : 'alert-info'; ?>">
                            <h6><i class="fas fa-calculator me-2"></i>Chi tiết tính toán:</h6>
                            <p class="mb-0"><strong><?php echo $ketQua['chi_tiet']['cong_thuc']; ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal chuyển đổi mã chuyến -->
<div class="modal fade" id="tripChangeModal" tabindex="-1" aria-labelledby="tripChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tripChangeModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Di chuyển đoạn giữa các chuyến
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-ship me-2"></i>
                                    Chuyến hiện tại
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Tàu:</strong> <span id="currentShipName">-</span></p>
                                <p><strong>Mã chuyến:</strong> <span id="currentTripNumber">-</span></p>
                                <p><strong>Số đoạn:</strong> <span id="currentTripSegments">-</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Chọn chuyến khác
                                </h6>
                            </div>
                            <div class="card-body">
                                <label for="newTripSelect" class="form-label">Chọn mã chuyến:</label>
                                <select class="form-select" id="newTripSelect">
                                    <option value="">-- Đang tải danh sách --</option>
                                </select>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-success" onclick="changeTrip()" id="btnConfirmChange" disabled>
                                        <i class="fas fa-arrow-right me-1"></i>
                                        Di chuyển đoạn
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danh sách các đoạn của chuyến được chọn -->
                <div class="mt-3" id="selectedTripInfo" style="display: none;">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Chọn đoạn để chuyển sang
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Lưu ý:</strong> Chọn đoạn để di chuyển sang chuyến khác. 
                                Đoạn được chọn sẽ được chuyển từ chuyến hiện tại sang chuyến đích.
                            </div>
                            <div id="selectedTripDetails">
                                <!-- Danh sách đoạn sẽ được load động -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Hủy
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa đoạn đã được loại bỏ theo yêu cầu -->

<!-- Modal: Quản lý số chuyến (Thêm/Xóa chuyến) -->
<div class="modal fade" id="insertTripModal" tabindex="-1" aria-labelledby="insertTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="insertTripModalLabel">
                    <i class="fas fa-list-ol me-2"></i>
                    Quản lý số chuyến
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Thông tin tàu -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-ship me-2"></i>
                            Thông tin tàu
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Tàu:</strong> <span id="insertTripShipName">-</span></p>
                        <p class="mb-0"><strong>Danh sách chuyến hiện có:</strong> <span id="insertTripCurrentTrips" class="badge bg-secondary">-</span></p>
                    </div>
                </div>

                <!-- Tabs: Thêm chuyến / Xóa chuyến -->
                <ul class="nav nav-tabs" id="manageTripTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-insert-trip" data-bs-toggle="tab" data-bs-target="#panel-insert-trip" type="button" role="tab">
                            <i class="fas fa-plus-circle me-1 text-success"></i>Thêm chuyến giữa
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-delete-trip" data-bs-toggle="tab" data-bs-target="#panel-delete-trip" type="button" role="tab">
                            <i class="fas fa-trash-alt me-1 text-danger"></i>Xóa chuyến
                        </button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 rounded-bottom p-3" id="manageTripTabContent">
                    <!-- Tab: Thêm chuyến giữa -->
                    <div class="tab-pane fade show active" id="panel-insert-trip" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Chức năng:</strong> Chèn một chuyến mới vào giữa các chuyến hiện có.
                            Hệ thống sẽ tự động đổi số các chuyến phía sau để tạo khoảng trống.
                        </div>

                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-plus me-2"></i>
                                    Chọn vị trí chuyến mới
                                </h6>
                            </div>
                            <div class="card-body">
                                <label for="insertTripPosition" class="form-label">
                                    <strong>Vị trí chuyến muốn thêm:</strong>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="insertTripPosition"
                                       min="1" step="1" placeholder="Nhập số chuyến (VD: 4)">
                                <div class="form-text">
                                    <i class="fas fa-lightbulb text-warning me-1"></i>
                                    Nhập số chuyến bạn muốn tạo. Tất cả chuyến >= số này sẽ được tăng thêm 1.
                                </div>
                                <div class="mt-3" id="insertTripPreview" style="display: none;">
                                    <div class="alert alert-success mb-0">
                                        <h6><i class="fas fa-eye me-2"></i>Xem trước thay đổi:</h6>
                                        <div id="insertTripPreviewContent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-success" onclick="confirmInsertTrip()" id="btnConfirmInsert" disabled>
                                <i class="fas fa-check me-1"></i>
                                Xác nhận thêm chuyến
                            </button>
                        </div>
                    </div>

                    <!-- Tab: Xóa chuyến -->
                    <div class="tab-pane fade" id="panel-delete-trip" role="tabpanel">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Cảnh báo:</strong> Xóa chuyến sẽ xóa TẤT CẢ dữ liệu của chuyến đó (các đoạn, lệnh cấp thêm).
                            Các chuyến phía sau sẽ được giảm số tự động.
                        </div>

                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-trash me-2"></i>
                                    Chọn chuyến cần xóa
                                </h6>
                            </div>
                            <div class="card-body">
                                <label for="deleteTripNumber" class="form-label">
                                    <strong>Số chuyến cần xóa:</strong>
                                </label>
                                <select class="form-select form-select-lg" id="deleteTripNumber">
                                    <option value="">-- Chọn chuyến --</option>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle text-info me-1"></i>
                                    Chọn chuyến muốn xóa. Tất cả dữ liệu của chuyến này sẽ bị xóa.
                                </div>
                                <div class="mt-3" id="deleteTripPreview" style="display: none;">
                                    <div class="alert alert-danger mb-0">
                                        <h6><i class="fas fa-eye me-2"></i>Xem trước thay đổi:</h6>
                                        <div id="deleteTripPreviewContent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-danger" onclick="confirmDeleteTrip()" id="btnConfirmDelete" disabled>
                                <i class="fas fa-trash me-1"></i>
                                Xác nhận xóa chuyến
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sắp xếp thứ tự đoạn trong chuyến -->
<div class="modal fade" id="reorderSegmentsModal" tabindex="-1" aria-labelledby="reorderSegmentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="reorderSegmentsModalLabel">
                    <i class="fas fa-sort me-2"></i>
                    Sắp xếp thứ tự đoạn trong chuyến
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Hướng dẫn:</strong> Kéo thả các đoạn để thay đổi thứ tự, hoặc dùng nút mũi tên.
                    <br>
                    <small><i class="fas fa-exclamation-triangle text-warning me-1"></i>Dầu cấp thêm sẽ tự động đi theo đoạn mà nó được gán.</small>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Tàu:</strong> <span id="reorderShipName">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Chuyến:</strong> <span id="reorderTripNumber">-</span></p>
                    </div>
                </div>

                <div id="reorderSegmentsList" class="list-group">
                    <!-- Danh sách đoạn sẽ được load động -->
                    <div class="text-center py-3">
                        <span class="spinner-border spinner-border-sm"></span> Đang tải...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Hủy
                </button>
                <button type="button" class="btn btn-warning" onclick="confirmReorderSegments()" id="btnConfirmReorder">
                    <i class="fas fa-check me-1"></i>
                    Lưu thứ tự mới
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Quản lý danh sách Điểm mới (đa điểm)
window.__lastFocusedDiemMoiInput = null;
function themDiemMoi(prefill) {
    const wrapper = document.getElementById('ds_diem_moi_wrapper');
    if (!wrapper) return;
    let pointValue = '';
    let reasonValue = '';
    if (prefill) {
        if (typeof prefill === 'object' && prefill !== null) {
            pointValue = prefill.point || '';
            reasonValue = prefill.reason || '';
        } else {
            pointValue = String(prefill || '');
        }
    }
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 diem-moi-item';
    row.innerHTML = `
        <div class="col-lg-5 col-md-6">
            <div class="position-relative">
                <input type="text" class="form-control diem-input" name="diem_moi[]"
                    placeholder="Điểm tiếp theo..." autocomplete="off"
                    onfocus="showAllDiem(this.nextElementSibling, '');"
                    oninput="searchDiem(this, this.nextElementSibling)">
                <div class="dropdown-menu diem-results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-5">
            <div class="reason-group">
                <input type="text" class="form-control form-control-sm diem-moi-reason" name="diem_moi_reason[]"
                    placeholder="Lý do thêm..." autocomplete="off">
                <div class="mt-1">
                    <button type="button" class="btn btn-primary btn-sm me-1" onclick="this.parentElement.previousElementSibling.value='Đổi lệnh'">Đổi lệnh</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="this.parentElement.previousElementSibling.value='Lãnh vật tư'">Lãnh vật tư</button>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-lg-end">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="xoaDiemMoi(this)"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
        </div>
    `;
    wrapper.appendChild(row);
    const input = row.querySelector('input[name="diem_moi[]"]');
    const reasonInput = row.querySelector('input[name="diem_moi_reason[]"]');
    if (input) {
        input.addEventListener('focus', function(){ window.__lastFocusedDiemMoiInput = input; });
        input.addEventListener('click', function(){ window.__lastFocusedDiemMoiInput = input; });
        if (pointValue) {
            input.value = pointValue;
            input.readOnly = true;
            input.placeholder = 'Đã chọn: ' + pointValue;
            input.dataset.prefilled = '1';
        }
    }
    if (reasonInput && reasonValue) {
        reasonInput.value = reasonValue;
    }
    updateDiemMoiPlaceholders();
}
function xoaDiemMoi(btn) {
    const item = btn.closest('.diem-moi-item');
    if (item) {
        const wrapper = document.getElementById('ds_diem_moi_wrapper');
        // Luôn giữ ít nhất 1 hàng để người dùng nhập
        if (wrapper && wrapper.querySelectorAll('.diem-moi-item').length <= 1) {
            const input = item.querySelector('input[name="diem_moi[]"]');
            const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
            if (input) {
                input.value = '';
                input.readOnly = false;
            }
            if (reasonInput) {
                reasonInput.value = '';
            }
            updateDiemMoiPlaceholders();
            return;
        }
        item.remove();
    }
    if (window.__lastFocusedDiemMoiInput && !document.body.contains(window.__lastFocusedDiemMoiInput)) {
        window.__lastFocusedDiemMoiInput = null;
    }
    updateDiemMoiPlaceholders();
}
function setDiemMoiReason(btn, reason) {
    const item = btn.closest('.diem-moi-item');
    if (!item) return;
    const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
    if (reasonInput) {
        reasonInput.value = reason;
        reasonInput.focus();
    }
}
function resetDiemDangChon() {
    let input = window.__lastFocusedDiemMoiInput;
    if (!input || !input.closest('#ds_diem_moi_wrapper')) {
        input = document.querySelector('#ds_diem_moi_wrapper input[name="diem_moi[]"]');
    }
    if (!input) return;
    const item = input.closest('.diem-moi-item');
    input.value = '';
    input.readOnly = false;
    input.dataset.prefilled = '';
    const dropdown = input.nextElementSibling;
    if (dropdown) {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
    }
    if (item) {
        const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
        if (reasonInput) reasonInput.value = '';
    }
    updateDiemMoiPlaceholders();
    input.focus();
}
function updateDiemMoiPlaceholders() {
    const rows = document.querySelectorAll('#ds_diem_moi_wrapper .diem-moi-item');
    rows.forEach((row, idx) => {
        const input = row.querySelector('input[name="diem_moi[]"]');
        if (!input) return;
        const placeholder = idx === 0 ? 'Điểm C - Bắt đầu nhập để tìm kiếm...' : 'Điểm tiếp theo...';
        if (!input.readOnly || input.value === '') {
            input.placeholder = placeholder;
        }
        input.dataset.index = String(idx);
    });
}
// Khởi tạo ít nhất một ô Điểm mới khi mở modal/hiển thị vùng đổi lệnh
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.getElementById('ds_diem_moi_wrapper');
    if (wrapper) {
        const prefillNode = document.getElementById('prefilled_diem_moi_json');
        let prefilledList = [];
        if (prefillNode && prefillNode.value) {
            try {
                const parsed = JSON.parse(prefillNode.value);
                if (Array.isArray(parsed)) {
                    prefilledList = parsed.filter(item => {
                        if (typeof item === 'string') {
                            return item.trim().length > 0;
                        }
                        if (item && typeof item === 'object') {
                            return String(item.point || '').trim().length > 0;
                        }
                        return false;
                    });
                }
            } catch(_){}
        }
        if (prefilledList.length > 0) {
            wrapper.innerHTML = '';
            prefilledList.forEach(item => themDiemMoi(item));
        }
        if (wrapper.querySelectorAll('.diem-moi-item').length === 0) {
            themDiemMoi();
        } else {
            updateDiemMoiPlaceholders();
        }
    }
});

// ========== INSERT TRIP FUNCTIONALITY ==========

let insertTripData = {
    tenTau: '',
    currentTrips: [],
    maxTrip: 0
};

// Mở modal Insert Trip
function openInsertTripModal() {
    const tenTau = document.getElementById('ten_tau').value;

    if (!tenTau) {
        showAlert('Vui lòng chọn tàu trước', 'warning');
        return;
    }

    insertTripData.tenTau = tenTau;

    // Hiển thị modal
    const modal = new bootstrap.Modal(document.getElementById('insertTripModal'));
    modal.show();

    // Cập nhật thông tin tàu
    document.getElementById('insertTripShipName').textContent = tenTau;
    document.getElementById('insertTripCurrentTrips').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang tải...';

    // Reset input
    document.getElementById('insertTripPosition').value = '';
    document.getElementById('insertTripPreview').style.display = 'none';
    document.getElementById('btnConfirmInsert').disabled = true;

    // Lấy danh sách chuyến hiện có
    fetch('ajax/get_trips.php?ten_tau=' + encodeURIComponent(tenTau))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                insertTripData.currentTrips = data.trips || [];
                insertTripData.maxTrip = data.max_trip || 0;

                // Hiển thị danh sách chuyến
                if (insertTripData.currentTrips.length === 0) {
                    document.getElementById('insertTripCurrentTrips').innerHTML = '<span class="badge bg-secondary">Chưa có chuyến nào</span>';
                } else {
                    const tripBadges = insertTripData.currentTrips.map(t =>
                        '<span class="badge bg-primary me-1">' + t + '</span>'
                    ).join('');
                    document.getElementById('insertTripCurrentTrips').innerHTML = tripBadges;
                }

                // Gợi ý vị trí insert
                const suggestPosition = insertTripData.maxTrip + 1;
                document.getElementById('insertTripPosition').placeholder = 'Nhập số chuyến (VD: ' + suggestPosition + ')';

                // Populate dropdown xóa chuyến
                const deleteSelect = document.getElementById('deleteTripNumber');
                deleteSelect.innerHTML = '<option value="">-- Chọn chuyến --</option>';
                if (insertTripData.currentTrips.length > 0) {
                    insertTripData.currentTrips.forEach(trip => {
                        deleteSelect.innerHTML += '<option value="' + trip + '">Chuyến ' + trip + '</option>';
                    });
                }
                // Reset preview xóa
                document.getElementById('deleteTripPreview').style.display = 'none';
                document.getElementById('btnConfirmDelete').disabled = true;
            } else {
                showAlert('Không thể tải danh sách chuyến: ' + (data.error || 'Lỗi không xác định'), 'error');
            }
        })
        .catch(error => {
            console.error('Error loading trips:', error);
            showAlert('Lỗi khi tải danh sách chuyến', 'error');
        });
}

// Preview thay đổi khi nhập vị trí
document.addEventListener('DOMContentLoaded', function() {
    const insertPositionInput = document.getElementById('insertTripPosition');
    if (insertPositionInput) {
        insertPositionInput.addEventListener('input', function() {
            const position = parseInt(this.value);
            const previewDiv = document.getElementById('insertTripPreview');
            const previewContent = document.getElementById('insertTripPreviewContent');
            const btnConfirm = document.getElementById('btnConfirmInsert');

            if (!position || position <= 0 || isNaN(position)) {
                previewDiv.style.display = 'none';
                btnConfirm.disabled = true;
                return;
            }

            // Kiểm tra vị trí hợp lệ
            if (position > insertTripData.maxTrip + 1) {
                previewContent.innerHTML = '<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-2"></i><strong>Lỗi:</strong> Vị trí quá xa. Chuyến cao nhất hiện tại là <strong>' + insertTripData.maxTrip + '</strong>. Vui lòng chọn từ 1 đến ' + (insertTripData.maxTrip + 1) + '.</p>';
                previewDiv.style.display = 'block';
                btnConfirm.disabled = true;
                return;
            }

            // Tìm các chuyến bị ảnh hưởng
            const affectedTrips = insertTripData.currentTrips.filter(t => t >= position).sort((a, b) => b - a);

            if (affectedTrips.length === 0) {
                previewContent.innerHTML = '<p class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Không có chuyến nào cần đổi số. Vị trí <strong>' + position + '</strong> sẵn sàng để tạo chuyến mới.</p>';
                btnConfirm.disabled = false;
            } else {
                let html = '<p><i class="fas fa-sync-alt text-primary me-2"></i>Các chuyến sau sẽ được đổi số:</p><ul class="mb-0">';
                affectedTrips.forEach(trip => {
                    html += '<li><strong>Chuyến ' + trip + '</strong> → <strong class="text-success">Chuyến ' + (trip + 1) + '</strong></li>';
                });
                html += '</ul><p class="mt-2 mb-0 text-success"><i class="fas fa-arrow-right me-2"></i>Sau đó bạn có thể tạo <strong>Chuyến ' + position + '</strong> mới.</p>';
                previewContent.innerHTML = html;
                btnConfirm.disabled = false;
            }

            previewDiv.style.display = 'block';
        });
    }

    // Preview thay đổi khi chọn chuyến xóa
    const deleteTripSelect = document.getElementById('deleteTripNumber');
    if (deleteTripSelect) {
        deleteTripSelect.addEventListener('change', function() {
            const tripToDelete = parseInt(this.value);
            const previewDiv = document.getElementById('deleteTripPreview');
            const previewContent = document.getElementById('deleteTripPreviewContent');
            const btnConfirm = document.getElementById('btnConfirmDelete');

            if (!tripToDelete || isNaN(tripToDelete)) {
                previewDiv.style.display = 'none';
                btnConfirm.disabled = true;
                return;
            }

            // Tìm các chuyến sẽ được đổi số (giảm 1)
            const affectedTrips = insertTripData.currentTrips.filter(t => t > tripToDelete).sort((a, b) => a - b);

            let html = '<p class="mb-2"><i class="fas fa-trash text-danger me-2"></i><strong>Chuyến ' + tripToDelete + '</strong> sẽ bị xóa hoàn toàn.</p>';

            if (affectedTrips.length > 0) {
                html += '<p><i class="fas fa-sync-alt text-primary me-2"></i>Các chuyến sau sẽ được đổi số:</p><ul class="mb-0">';
                affectedTrips.forEach(trip => {
                    html += '<li><strong>Chuyến ' + trip + '</strong> → <strong class="text-success">Chuyến ' + (trip - 1) + '</strong></li>';
                });
                html += '</ul>';
            } else {
                html += '<p class="mb-0 text-muted"><i class="fas fa-info-circle me-2"></i>Không có chuyến nào cần đổi số sau khi xóa.</p>';
            }

            previewContent.innerHTML = html;
            previewDiv.style.display = 'block';
            btnConfirm.disabled = false;
        });
    }
});

// Xác nhận insert trip
function confirmInsertTrip() {
    const position = parseInt(document.getElementById('insertTripPosition').value);

    if (!position || position <= 0 || isNaN(position)) {
        showAlert('Vui lòng nhập vị trí hợp lệ', 'warning');
        return;
    }

    if (position > insertTripData.maxTrip + 1) {
        showAlert('Vị trí không hợp lệ. Vui lòng chọn từ 1 đến ' + (insertTripData.maxTrip + 1), 'warning');
        return;
    }

    // Disable button để tránh click nhiều lần
    const btnConfirm = document.getElementById('btnConfirmInsert');
    btnConfirm.disabled = true;
    btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';

    // Gọi API
    const formData = new FormData();
    formData.append('ten_tau', insertTripData.tenTau);
    formData.append('insert_position', position);

    fetch('api/insert_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');

            // Đóng modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('insertTripModal'));
            modal.hide();

            // Reload trang để cập nhật danh sách chuyến
            setTimeout(() => {
                window.location.href = 'index.php?ten_tau=' + encodeURIComponent(insertTripData.tenTau) + '&so_chuyen=' + position + '&inserted=1';
            }, 1000);
        } else {
            showAlert('Lỗi: ' + (data.error || 'Không thể thêm chuyến'), 'error');
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fas fa-check me-1"></i>Xác nhận thêm chuyến';
        }
    })
    .catch(error => {
        console.error('Error inserting trip:', error);
        showAlert('Lỗi khi thêm chuyến', 'error');
        btnConfirm.disabled = false;
        btnConfirm.innerHTML = '<i class="fas fa-check me-1"></i>Xác nhận thêm chuyến';
    });
}

// Xác nhận xóa chuyến
function confirmDeleteTrip() {
    const tripToDelete = parseInt(document.getElementById('deleteTripNumber').value);

    if (!tripToDelete || isNaN(tripToDelete)) {
        showAlert('Vui lòng chọn chuyến cần xóa', 'warning');
        return;
    }

    // Hiển thị confirm dialog
    if (!confirm('Bạn có chắc chắn muốn xóa Chuyến ' + tripToDelete + '?\n\nTẤT CẢ dữ liệu của chuyến này (các đoạn, lệnh cấp thêm) sẽ bị xóa vĩnh viễn.\n\nHành động này KHÔNG thể hoàn tác!')) {
        return;
    }

    // Disable button để tránh click nhiều lần
    const btnConfirm = document.getElementById('btnConfirmDelete');
    btnConfirm.disabled = true;
    btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xóa...';

    // Gọi API
    const formData = new FormData();
    formData.append('ten_tau', insertTripData.tenTau);
    formData.append('delete_trip', tripToDelete);

    fetch('api/delete_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');

            // Đóng modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('insertTripModal'));
            modal.hide();

            // Reload trang với chuyến trước đó (nếu có)
            const newTrip = tripToDelete > 1 ? tripToDelete - 1 : 1;
            setTimeout(() => {
                window.location.href = 'index.php?ten_tau=' + encodeURIComponent(insertTripData.tenTau) + '&so_chuyen=' + newTrip + '&deleted=1';
            }, 1000);
        } else {
            showAlert('Lỗi: ' + (data.error || 'Không thể xóa chuyến'), 'error');
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fas fa-trash me-1"></i>Xác nhận xóa chuyến';
        }
    })
    .catch(error => {
        console.error('Error deleting trip:', error);
        showAlert('Lỗi khi xóa chuyến', 'error');
        btnConfirm.disabled = false;
        btnConfirm.innerHTML = '<i class="fas fa-trash me-1"></i>Xác nhận xóa chuyến';
    });
}

// ============================================
// REORDER SEGMENTS - Sắp xếp thứ tự đoạn
// ============================================
let reorderData = {
    tenTau: '',
    soChuyen: 0,
    segments: [],
    originalOrder: []
};

function openReorderModal() {
    const tenTau = document.getElementById('ten_tau').value;
    const soChuyen = document.getElementById('so_chuyen').value;

    if (!tenTau || !soChuyen) {
        showAlert('Vui lòng chọn tàu và chuyến trước', 'warning');
        return;
    }

    reorderData.tenTau = tenTau;
    reorderData.soChuyen = parseInt(soChuyen);

    // Hiển thị modal
    const modal = new bootstrap.Modal(document.getElementById('reorderSegmentsModal'));
    modal.show();

    // Cập nhật thông tin
    document.getElementById('reorderShipName').textContent = tenTau;
    document.getElementById('reorderTripNumber').textContent = soChuyen;
    document.getElementById('reorderSegmentsList').innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Đang tải...</div>';

    // Load danh sách đoạn
    loadReorderSegments();
}

function loadReorderSegments() {
    fetch(`ajax/get_trip_details.php?ten_tau=${encodeURIComponent(reorderData.tenTau)}&so_chuyen=${reorderData.soChuyen}`)
        .then(response => response.json())
        .then(data => {
            // Dùng all_segments để sắp xếp cả đoạn thường và lệnh cấp thêm
            if (data.success && data.all_segments && data.all_segments.length > 0) {
                reorderData.segments = data.all_segments;
                reorderData.capThem = data.cap_them || [];
                reorderData.originalOrder = data.all_segments.map(s => s.___idx);
                renderReorderList();
            } else {
                document.getElementById('reorderSegmentsList').innerHTML =
                    '<div class="alert alert-warning">Không có đoạn nào trong chuyến này</div>';
            }
        })
        .catch(error => {
            console.error('Error loading segments:', error);
            document.getElementById('reorderSegmentsList').innerHTML =
                '<div class="alert alert-danger">Lỗi khi tải danh sách đoạn</div>';
        });
}

function renderReorderList() {
    const container = document.getElementById('reorderSegmentsList');
    let html = '';

    reorderData.segments.forEach((seg, index) => {
        const isCapThem = parseInt(seg.cap_them || 0) === 1;
        const diemDi = seg.diem_di || '-';
        const diemDen = seg.diem_den || '-';
        const khoiLuong = parseFloat(seg.khoi_luong_van_chuyen_t) || 0;
        const dauTinhToan = parseFloat(seg.dau_tinh_toan_lit) || 0;
        const soLuongCapThem = parseFloat(seg.so_luong_cap_them_lit) || 0;
        const lyDoCapThem = seg.ly_do_cap_them || '';
        const ngayDi = seg.ngay_di || '';
        const ngayDen = seg.ngay_den || '';

        html += `
        <div class="list-group-item reorder-item ${isCapThem ? 'bg-warning bg-opacity-10' : ''}" data-idx="${seg.___idx}" data-index="${index}">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <button class="btn btn-sm btn-outline-secondary" onclick="moveSegmentUp(${index})" ${index === 0 ? 'disabled' : ''}>
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="moveSegmentDown(${index})" ${index === reorderData.segments.length - 1 ? 'disabled' : ''}>
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold">
                        <span class="badge ${isCapThem ? 'bg-warning text-dark' : 'bg-primary'} me-2">${index + 1}</span>
                        ${isCapThem
                            ? `<span class="badge bg-warning text-dark me-1"><i class="fas fa-gas-pump"></i> Cấp thêm</span> ${lyDoCapThem || 'Dầu cấp thêm'}`
                            : `${diemDi} → ${diemDen}`
                        }
                    </div>
                    <small class="text-muted">
                        ${isCapThem
                            ? `<i class="fas fa-gas-pump me-1"></i>Số lượng: ${soLuongCapThem.toLocaleString('vi-VN')} lít`
                            : `${ngayDi ? `<i class="fas fa-calendar-alt me-1"></i>${ngayDi}` : ''}
                               ${ngayDen ? ` → ${ngayDen}` : ''}
                               ${khoiLuong > 0 ? ` | KL: ${khoiLuong.toLocaleString('vi-VN')} tấn` : ''}
                               | Dầu: ${dauTinhToan.toLocaleString('vi-VN')} lít`
                        }
                    </small>
                </div>
                <div class="text-muted">
                    <i class="fas fa-grip-vertical"></i>
                </div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

function moveSegmentUp(index) {
    if (index <= 0) return;

    // Swap in array
    [reorderData.segments[index - 1], reorderData.segments[index]] =
    [reorderData.segments[index], reorderData.segments[index - 1]];

    renderReorderList();
}

function moveSegmentDown(index) {
    if (index >= reorderData.segments.length - 1) return;

    // Swap in array
    [reorderData.segments[index], reorderData.segments[index + 1]] =
    [reorderData.segments[index + 1], reorderData.segments[index]];

    renderReorderList();
}

function confirmReorderSegments() {
    const newOrder = reorderData.segments.map(s => s.___idx);

    // Check if order changed
    const orderChanged = JSON.stringify(newOrder) !== JSON.stringify(reorderData.originalOrder);
    if (!orderChanged) {
        showAlert('Thứ tự không thay đổi', 'info');
        return;
    }

    const btnConfirm = document.getElementById('btnConfirmReorder');
    btnConfirm.disabled = true;
    btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...';

    fetch('api/reorder_segments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            ten_tau: reorderData.tenTau,
            so_chuyen: reorderData.soChuyen,
            new_order: newOrder
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');

            // Đóng modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('reorderSegmentsModal'));
            modal.hide();

            // Reload trang với params để giữ lại chuyến vừa edit
            setTimeout(() => {
                window.location.href = 'index.php?ten_tau=' + encodeURIComponent(reorderData.tenTau) + '&so_chuyen=' + reorderData.soChuyen;
            }, 800);
        } else {
            showAlert('Lỗi: ' + (data.error || 'Không thể sắp xếp lại'), 'error');
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fas fa-check me-1"></i>Lưu thứ tự mới';
        }
    })
    .catch(error => {
        console.error('Error reordering:', error);
        showAlert('Lỗi khi sắp xếp lại đoạn', 'error');
        btnConfirm.disabled = false;
        btnConfirm.innerHTML = '<i class="fas fa-check me-1"></i>Lưu thứ tự mới';
    });
}

</script>