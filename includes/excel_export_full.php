<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../src/Report/HeaderTemplate.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Report\HeaderTemplate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Helper functions for Excel export
function toIntHelper($v){ return (int)floor((float)$v); }

/**
 * Set integer value in Excel cell with proper formatting
 * - Làm tròn xuống (floor) thành số nguyên
 * - Nếu = 0 thì để ô trống
 * - Áp dụng format #,##0 (dấu chấm phân cách nghìn)
 */
function setIntHelper($sheet,$col,$row,$val,$showDashForZero=false){
    $n=(int)floor((float)$val);
    if($n===0){
        if($showDashForZero){
            $sheet->setCellValueExplicitByColumnAndRow($col, $row, '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->setCellValueByColumnAndRow($col,$row,'');
        }
        return;
    }
    $sheet->setCellValueByColumnAndRow($col,$row,$n);
    $sheet->getStyleByColumnAndRow($col,$row)->getNumberFormat()->setFormatCode('#,##0');
}

/**
 * Set decimal value in Excel cell with proper formatting
 * - Giữ phần thập phân
 * - Nếu = 0 thì để ô trống
 * - Áp dụng format #,##0.00 (dấu chấm phân cách nghìn, dấu phẩy thập phân sẽ do Excel locale quyết định)
 */
function setDecimalHelper($sheet,$col,$row,$val,$decimals=2){
    $v=(float)$val;
    if($v==0){
        // Để ô trống khi giá trị = 0
        $sheet->setCellValueExplicitByColumnAndRow($col, $row, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        return; 
    }
    $sheet->setCellValueByColumnAndRow($col,$row,$v);
    $formatCode = '#,##0' . ($decimals > 0 ? ('.' . str_repeat('0', $decimals)) : '');
    $sheet->getStyleByColumnAndRow($col,$row)->getNumberFormat()->setFormatCode($formatCode); 
}

function exportLichSuFull($groups, $currentMonth, $currentYear, $isDetailedExport = false) {
    if (!headers_sent()) { @header('X-Export-Enter: 1'); }
    if (empty($groups) || !is_array($groups)) {
        die('<pre style="color:red;font-size:16px;">LỖI: Dữ liệu xuất Excel rỗng hoặc không hợp lệ. $groups=' . htmlspecialchars(var_export($groups, true)) . '</pre>');
    }
    // Model lấy số đăng ký và dầu tồn
    require_once __DIR__ . '/../models/TauPhanLoai.php';
    require_once __DIR__ . '/../models/DauTon.php';
    require_once __DIR__ . '/../models/LuuKetQua.php';
    $tauModel = class_exists('TauPhanLoai') ? new \TauPhanLoai() : null;
    $dauTonModel = new \DauTon();
    $ketQuaModel = new \LuuKetQua();

    $spreadsheet = new Spreadsheet();
    while ($spreadsheet->getSheetCount() > 0) { $spreadsheet->removeSheetByIndex(0); }
    $sheetAdded = false;

    // Nếu yêu cầu xuất chi tiết theo tàu (IN TINH DAU) → chỉ tạo các sheet chi tiết và bỏ qua các sheet tổng hợp
    if ($isDetailedExport) {
        $templatePath = HeaderTemplate::pathFor('IN_TINH_DAU');
        if (!$templatePath || !file_exists($templatePath)) {
            die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePath) . '</pre>');
        }
        $defaultCellStyle = ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ];
        // Danh sách tàu được chọn từ request
        $selectedShips = [];
        if (isset($_GET['extra_ships']) && is_array($_GET['extra_ships'])) {
            foreach ($_GET['extra_ships'] as $s) {
                $s = trim((string)$s);
                if ($s !== '') { $selectedShips[strtolower(trim($s, '"'))] = $s; }
            }
        }
        // Nếu người dùng có lọc theo tên tàu ở bộ lọc chính, đảm bảo cũng nằm trong tập chọn
        $shipFilter = isset($_GET['ten_phuong_tien']) ? trim((string)$_GET['ten_phuong_tien']) : '';
        if ($shipFilter !== '') { $selectedShips[strtolower(trim($shipFilter, '"'))] = $shipFilter; }

        // Gom dữ liệu theo tàu và phân loại để render
        $rowsByShip = [];
        $plByShip = [];
        foreach ($groups as $phanLoai => $rowsInGroup) {
            foreach ($rowsInGroup as $r) {
                $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
                if ($ship === '') continue;
                $shipKey = strtolower(trim($ship, '"'));
                // Nếu có selectedShips thì chỉ lấy những tàu được chọn
                if (!empty($selectedShips) && !isset($selectedShips[$shipKey])) continue;
                if (!isset($rowsByShip[$ship])) $rowsByShip[$ship] = [];
                $rowsByShip[$ship][] = $r;
                $plByShip[$ship] = ($phanLoai === 'thue_ngoai') ? 'SLN' : 'SLCTY';
            }
        }

        // Tạo từng sheet chi tiết theo tàu
        foreach ($rowsByShip as $ship => $rows) {
            $tmpSpreadsheet = IOFactory::load($templatePath);
            $sheet = $tmpSpreadsheet->getSheet(0);
            // Điền ngày hệ thống vào header (dòng 4: "Tp. Hồ Chí Minh, ngày XX tháng XX năm XXXX")
            HeaderTemplate::applyCommonHeader($sheet, 'G4');

            $suffix = $plByShip[$ship] ?? 'SLCTY';
            $sheetName = 'IN TINH DAU-' . $suffix . ' - ' . $ship;
            $sheet->setTitle(mb_substr($sheetName, 0, 31)); // Excel sheet name <= 31 ký tự

            // Ghi tiêu đề vào dòng 6 (A6:I6 merged trong template)
            $sheet->setCellValue('A6', 'BÁO CÁO TÍNH DẦU SÀ LAN TỰ HÀNH ' . $ship);
            $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(13);
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Xác định khoảng ngày hiển thị cho sheet chi tiết của tàu này
            // Bắt đầu từ ngày "Bảng tính ngày" (nếu người dùng truyền notai_date hoặc tu_ngay), kết thúc là ngày hiện tại
            // 1) Tìm ngày sớm nhất trong dữ liệu (ngày đi)
            $firstDateInData = null;
            foreach ($rows as $r0) {
                $d0 = trim((string)($r0['ngay_di'] ?? ''));
                if ($d0 === '') continue;
                $iso0 = parse_date_vn($d0);
                if ($iso0 && ($firstDateInData === null || $iso0 < $firstDateInData)) {
                    $firstDateInData = $iso0;
                }
            }
            // Mặc định: nếu không có ngày trong dữ liệu thì lấy đầu năm hiện tại
            $startDateIso = $firstDateInData ?: date('Y-01-01');
            // Ưu tiên: notai_date theo từng tàu hoặc toàn cục
            if (!empty($_GET['notai_date']) && is_array($_GET['notai_date'])) {
                $tmpVN = trim((string)($_GET['notai_date'][$ship] ?? ''));
                $tmpIso = $tmpVN !== '' ? (parse_date_vn($tmpVN) ?: '') : '';
                if ($tmpIso !== '') { $startDateIso = $tmpIso; }
            } elseif (!empty($_GET['notai_date'])) {
                $tmpVN = trim((string)$_GET['notai_date']);
                $tmpIso = $tmpVN !== '' ? (parse_date_vn($tmpVN) ?: '') : '';
                if ($tmpIso !== '') { $startDateIso = $tmpIso; }
            } elseif (!empty($_GET['tu_ngay'])) {
                $tmpIso = parse_date_vn((string)$_GET['tu_ngay']);
                if ($tmpIso) { $startDateIso = $tmpIso; }
            }
            $ngayBangTinhIso = $startDateIso;
            $ngayBangTinhVN  = format_date_vn($ngayBangTinhIso);
            $endDateIso = date('Y-m-d');

            // Lọc các dòng theo khoảng ngày [Bảng tính ngày .. hôm nay]
            $rows = array_values(array_filter($rows, function($r) use ($startDateIso, $endDateIso){
                $d = trim((string)($r['ngay_di'] ?? ''));
                if ($d === '') return false;
                $iso = parse_date_vn($d);
                if (!$iso) return false;
                return ($iso >= $startDateIso && $iso <= $endDateIso);
            }));

            // Sắp xếp theo tên tàu -> số chuyến -> ___idx
            usort($rows, function($a,$b){
                // 1. Tên tàu
                $ta=mb_strtolower(trim($a['ten_phuong_tien']??''));
                $tb=mb_strtolower(trim($b['ten_phuong_tien']??''));
                if($ta!==$tb) return $ta<=>$tb;

                // 2. Số chuyến (để chuyến 5 nằm đúng giữa 4 và 6)
                $tripA=(int)($a['so_chuyen']??0);
                $tripB=(int)($b['so_chuyen']??0);
                if($tripA!==$tripB) return $tripA<=>$tripB;

                // 3. Thứ tự nhập liệu (___idx) - cho các đoạn trong cùng 1 chuyến
                $idxA=(float)($a['___idx']??0);
                $idxB=(float)($b['___idx']??0);
                return $idxA<=>$idxB;
            });

            // Dữ liệu bắt đầu từ dòng 9 vì template đã có header cột ở dòng 8
            $currentRow = 9; $stt = 1; $displayedTrips = [];
            $sumKm = 0; $sumFuel = 0;
            foreach ($rows as $r) {
                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                $isChuyenDau = (int)($r['cap_them'] ?? 0) === 2;

                // Bỏ qua chuyển dầu - không hiển thị trong báo cáo chi tiết
                if ($isChuyenDau) {
                    continue;
                }

                $tripCode = (string)($r['so_chuyen'] ?? '');
                $soChuyenDisplay = '';
                if (!$isCapThem && $tripCode !== '') {
                    if (!isset($displayedTrips[$tripCode])) {
                        $displayedTrips[$tripCode] = count($displayedTrips) + 1;
                        $soChuyenDisplay = (string)$displayedTrips[$tripCode];
                    }
                }

                if ($isCapThem) {
                    $fuel = (float)($r['so_luong_cap_them_lit'] ?? 0);
                    $kl = 0; $totalKm = 0;
                    $loaiHang = '';
                    $route = trim((string)($r['ly_do_cap_them'] ?? ''));
                    $dateVN = ''; // Ẩn ngày cho dòng Cấp thêm khi xuất (không ảnh hưởng dữ liệu gốc)
                } else {
                    $sch = (float)($r['cu_ly_co_hang_km'] ?? 0);
                    $skh = (float)($r['cu_ly_khong_hang_km'] ?? 0);
                    $kkh = (float)($r['he_so_khong_hang'] ?? 0);
                    $kch = (float)($r['he_so_co_hang'] ?? 0);
                    $kl  = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
                    $fuelStored = (float)($r['dau_tinh_toan_lit'] ?? 0);
                    $fuel = $fuelStored > 0 ? $fuelStored : (($skh * $kkh) + ($sch * $kl * $kch));
                    // Xây tuyến đường, ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
                    $route = trim((string)($r['route_hien_thi'] ?? ''));
                    if ($route === '') {
                        // Fallback: xây dựng tuyến từ các điểm riêng lẻ hoặc tuyen_duong
                        $route = trim((string)($r['tuyen_duong'] ?? ''));
                        if ($route === '') {
                            $isDoiLenh = (($r['doi_lenh'] ?? '0') == '1');
                            $di = trim((string)($r['diem_di'] ?? ''));
                            $den = trim((string)($r['diem_den'] ?? ''));
                            $b   = trim((string)($r['diem_du_kien'] ?? ''));
                            if ($isDoiLenh && ($di !== '' || $b !== '' || $den !== '')) {
                                $route = $di . ' → ' . $b . ' (đổi lệnh) → ' . $den;
                            } else if ($di !== '' || $den !== '') {
                                $route = $di . ' → ' . $den;
                            } else {
                                // Chế độ nhập thủ công: không có điểm đi/đến → lấy từ ghi chú (lưu nguyên văn)
                                $route = trim((string)($r['ghi_chu'] ?? ''));
                            }
                        }
                    }
                    $dateVN = format_date_vn((string)($r['ngay_di'] ?? ''));
                    $totalKm = (int)round($sch + $skh);
                    // Chỉ hiển thị loại hàng khi có hàng (kl > 0), không hàng thì để trống
                    $loaiHang = ($kl > 0) ? (string)($r['loai_hang'] ?? '') : '';
                }

                $fuelDisplay = (int)floor($fuel);
                $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt);
                $sheet->setCellValueByColumnAndRow(2,$currentRow,$soChuyenDisplay);
                // KLVC: Giữ phần thập phân, format #,##0.00
                setDecimalHelper($sheet,3,$currentRow,$kl,2);
                $sheet->setCellValueByColumnAndRow(4,$currentRow,$loaiHang);
                $sheet->setCellValueByColumnAndRow(5,$currentRow,$route);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,$dateVN);
                // Cự ly: Làm tròn xuống, để trống nếu = 0
                setIntHelper($sheet,7,$currentRow,$totalKm);
                // Dầu: Làm tròn xuống, để trống nếu = 0
                setIntHelper($sheet,8,$currentRow,$fuelDisplay);
                $sheet->setCellValueByColumnAndRow(9,$currentRow,(string)($r['ghi_chu'] ?? ''));
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($defaultCellStyle);
                // Căn giữa cho STT, Số chuyến, Cự ly
                foreach([1,2,7] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
                $stt++; $currentRow++;

                $sumKm += (int)$totalKm; $sumFuel += $fuelDisplay;
            }

            // Dòng tổng cộng (text ở cột E thay vì D)
            $sheet->setCellValueByColumnAndRow(2,$currentRow,count($displayedTrips));
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Tổng cộng:');
            setIntHelper($sheet,7,$currentRow,$sumKm);
            setIntHelper($sheet,8,$currentRow,$sumFuel);
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,[
                'font'=>['bold'=>true],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]
            ]));
            // Căn giữa cho STT, Số chuyến, Cự ly
            foreach([1,2,7] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
            $currentRow++;
            // Thêm một dòng trống ngăn cách bảng và phần phụ (Nợ tại...)
            $currentRow++;

            // ====================== Các dòng phụ: Nợ tại / Nhận dầu tại / Cộng ======================
            // Dùng đúng "Bảng tính ngày" đã xác định ở trên (startDateIso)
            $ngayBangTinhIso = $startDateIso;
            $ngayBangTinhVN  = format_date_vn($ngayBangTinhIso);

            // Nợ tại: cho phép override qua notai_* giống luồng XML và suy ra tồn đầu kỳ từ nhật ký
            $notaiDateOverrideVN = '';
            $notaiAmountOverride = '';
            if (!empty($_GET['notai_date']) && is_array($_GET['notai_date'])) {
                $notaiDateOverrideVN = trim((string)($_GET['notai_date'][$ship] ?? ''));
            } elseif (isset($_GET['notai_date'])) {
                $notaiDateOverrideVN = trim((string)$_GET['notai_date']);
            }
            if (!empty($_GET['notai_amount']) && is_array($_GET['notai_amount'])) {
                $notaiAmountOverride = trim((string)($_GET['notai_amount'][$ship] ?? ''));
            } elseif (isset($_GET['notai_amount'])) {
                $notaiAmountOverride = trim((string)$_GET['notai_amount']);
            }
            if ($notaiDateOverrideVN !== '') {
                $parsed = parse_date_vn($notaiDateOverrideVN);
                if ($parsed) { $ngayBangTinhVN = $notaiDateOverrideVN; $ngayBangTinhIso = $parsed; }
            }

            // Thu thập lần nhận dầu trong khoảng [Bảng tính ngày .. hôm nay]
            $receiptEntries = [];
            $transferOutEntries = []; // Phần chuyển dầu cho tàu khác (số âm)
            // Tồn đầu kỳ (mặc định 0; có thể override bằng tham số notai_amount)
            $tonDau = 0.0;
            // Ngày hiện tại để filter nhận dầu
            $todayIso = date('Y-m-d');
            foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
                $ngay = (string)($gd['ngay'] ?? '');
                if (!$ngay) continue;
                $ngayIso = parse_date_vn($ngay) ?: $ngay;
                // Chỉ lấy nhận dầu có ngày trong [start .. today]
                if ($ngayIso < $ngayBangTinhIso || $ngayIso > $todayIso) continue;

                $loai = strtolower((string)($gd['loai'] ?? ''));
                if ($loai === 'cap_them') {
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    if ($soLuong !== 0.0) {
                        $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
                        $receiptEntries[] = ['label' => $label, 'date' => $ngayIso, 'amount' => $soLuong];
                    }
                } elseif ($loai === 'tinh_chinh') {
                    // Phân biệt: Chuyển dầu (có transfer_pair_id) vs Tinh chỉnh thủ công (không có)
                    $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    if ($soLuong !== 0.0 && $transferPairId !== '') {
                        $lyDoGoc = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
                        if ($soLuong > 0) {
                            // Nhận dầu từ tàu khác (số dương) → Hiển thị trong "Nhận dầu tại"
                            // Đổi "Chuyển dầu ← nhận từ HTL-1" thành "Sà lan HTL-1"
                            $label = $lyDoGoc;
                            if (preg_match('/(?:nhận từ|nhan tu)\s*([A-Z0-9-]+)/iu', $lyDoGoc, $m)) {
                                $label = 'Sà lan ' . trim($m[1]);
                            }
                            $receiptEntries[] = ['label' => $label, 'date' => $ngayIso, 'amount' => $soLuong];
                        } else {
                            // Chuyển dầu cho tàu khác (số âm) → Tách riêng, không hiển thị trong "Nhận dầu tại"
                            $transferOutEntries[] = ['label' => $lyDoGoc, 'date' => $ngayIso, 'amount' => abs($soLuong)];
                        }
                    }
                    // Tinh chỉnh thủ công (không có transfer_pair_id) → BỎ QUA
                }
            }
            usort($receiptEntries, function($a,$b){ return strcmp($a['date'],$b['date']); });
            usort($transferOutEntries, function($a,$b){ return strcmp($a['date'],$b['date']); });

            // Row: Nợ tại
            // Theo mẫu: "Nợ tại" (cột D), "Bảng tính ngày" (cột E), ngày (cột F), số (cột G - căn phải)
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'Nợ tại');
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Bảng tính ngày');
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$ngayBangTinhVN);
            if ($notaiAmountOverride !== '') {
                $tonDau = (float)floor((float)str_replace(',','.', $notaiAmountOverride));
                setIntHelper($sheet,7,$currentRow,$tonDau);
            } else {
                setIntHelper($sheet,7,$currentRow,$tonDau);
            }
            // Không viền cho các dòng dưới "Tổng cộng", căn phải cột G (số liệu)
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
            ]);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            // Rows: Nhận dầu tại (mỗi entry một dòng)
            // Theo mẫu: "Nhận dầu tại" (cột D), tên cây xăng (cột E), ngày (cột F), số (cột G - căn phải)
            $sumReceiptsInt = 0;
            foreach ($receiptEntries as $rc) {
                $sheet->setCellValueByColumnAndRow(4,$currentRow,'Nhận dầu tại');
                $sheet->setCellValueByColumnAndRow(5,$currentRow,(string)$rc['label']);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,format_date_vn((string)$rc['date']));
                $valInt = (int)floor((float)$rc['amount']);
                setIntHelper($sheet,7,$currentRow,$valInt);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                    'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
                ]);
                // Căn phải cột G (số liệu)
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sumReceiptsInt += $valInt;
                $currentRow++;
            }

            // Rows: Chuyển cho tàu khác (mỗi entry một dòng) - hiển thị số âm
            $sumTransferOutInt = 0;
            foreach ($transferOutEntries as $tc) {
                $sheet->setCellValueByColumnAndRow(4,$currentRow,'Chuyển cho tàu');
                // Trích xuất tên tàu đích từ label
                $labelOut = (string)$tc['label'];
                if (preg_match('/(?:chuyển sang|chuyen sang)\s*([A-Z0-9-]+)/iu', $labelOut, $m)) {
                    $labelOut = trim($m[1]);
                }
                $sheet->setCellValueByColumnAndRow(5,$currentRow,$labelOut);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,format_date_vn((string)$tc['date']));
                $valInt = (int)floor((float)$tc['amount']);
                // Hiển thị số âm (trừ đi)
                $sheet->setCellValueByColumnAndRow(7,$currentRow,-$valInt);
                $sheet->getStyleByColumnAndRow(7,$currentRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                    'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
                ]);
                // Căn phải cột G (số liệu)
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sumTransferOutInt += $valInt;
                $currentRow++;
            }

            // Row: Cộng:
            // Theo mẫu: "Cộng:" (cột E), số (cột G - căn phải)
            // Công thức: Nợ + Nhận - Chuyển cho tàu khác
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Cộng:');
            $tongNoNhan = (int)floor($tonDau) + (int)$sumReceiptsInt - (int)$sumTransferOutInt;
            setIntHelper($sheet,7,$currentRow,$tongNoNhan);
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'font'=>['bold'=>true],
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
            ]);
            // Căn phải cột G (số liệu)
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            // ========== Dòng: Dầu tồn trên sà lan đến ngày ==========
            // Thêm một dòng trống ngăn cách trước khi hiển thị dầu tồn (giống XML)
            $currentRow++;
            // Sheet IN TINH DAU: Lấy tất cả data từ quá khứ đến NGÀY HIỆN TẠI
            // → Ngày phải là ngày hiện tại, KHÔNG PHẢI cuối tháng filter
            $dateForReport = date('Y-m-d'); // Ngày hiện tại
            $monthEndVN  = format_date_vn($dateForReport);
            // Tồn cuối = (Nợ tại + Nhận dầu) - Tổng dầu sử dụng hiển thị
            $tonCuoi = (int)floor($tongNoNhan - (int)$sumFuel);
            // Theo mẫu: "Dầu tồn trên sà lan đến ngày" (cột D-E merged), ngày (cột F), số (cột G - căn phải), "Lít" (cột H)
            // Merge D:E để text dài không bị cắt, giữ F riêng cho ngày
            $sheet->mergeCells("D{$currentRow}:E{$currentRow}");
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'Dầu tồn trên sà lan đến ngày');
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$monthEndVN);
            setIntHelper($sheet,7,$currentRow,$tonCuoi);
            $sheet->setCellValueByColumnAndRow(8,$currentRow,'Lít');
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'font'=>['bold'=>true],
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'horizontal'=>Alignment::HORIZONTAL_LEFT],
                'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_NONE]]
            ]);
            // Căn phải cột G (số liệu)
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet); $sheetAdded = true;
        }

        if(!$sheetAdded){ if (!headers_sent()) { @header('X-Export-Stop: no_detail_sheets'); } die('<pre style="color:red;font-size:16px;">LỖI: Không có tàu nào được chọn để xuất chi tiết.</pre>'); }

        // Xuất file (chỉ sheet chi tiết)
        $spreadsheet->setActiveSheetIndex(0);
        if(function_exists('ob_get_level')){ while(ob_get_level()>0){ @ob_end_clean(); } }
        @error_reporting(0);
        $filename = 'CT_T' . $currentMonth . '_' . $currentYear . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0'); header('Pragma: public');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); $writer->save('php://output');
        $spreadsheet->disconnectWorksheets(); unset($spreadsheet); exit();
    }

// ========== HÀM TẠO SHEET DAUTON (DẦU TỒN) ==========
function createDAUTONSheet($spreadsheet, $templatePath, $rowsInGroup, $currentMonth, $currentYear, $suffix, $tauModel, $dauTonModel, $ketQuaModel, $defaultCellStyle) {
    $tmpSpreadsheet = IOFactory::load($templatePath);
    $sheet = $tmpSpreadsheet->getSheet(0);
    $sheetName = 'DAUTON-' . $suffix;
    $sheet->setTitle($sheetName);

    // Header ngày theo template. Tiêu đề chia làm 2 dòng.
    HeaderTemplate::applyCommonHeader($sheet, 'D4');
    // Dòng 6: BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO
    $sheet->setCellValue('A6', 'BÁO CÁO NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO');
    $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A6:I6');
    // Dòng 7: THÁNG X NĂM XXXX
    $sheet->setCellValue('A7', 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear);
    $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A7:I7');

    // Gom theo tàu
    $ships = [];
    foreach($rowsInGroup as $row){ $ship = trim($row['ten_phuong_tien'] ?? ''); if($ship!==''){ $ships[$ship] = true; } }
    $ships = array_keys($ships); sort($ships);

    // Tính toán theo tháng
    $ngayDauThang = "$currentYear-" . sprintf('%02d', $currentMonth) . "-01";
    $ngayCuoiThang = date('Y-m-t', strtotime($ngayDauThang));
    $ngayCuoiThangVN = date('d-m-y', strtotime($ngayCuoiThang)); // dd-mm-yy format

    // Thêm dòng "THÁNG X-YYYY (dd-mm-yy)" ở dòng 10 (trong bảng, căn trái)
    $sheet->setCellValue('A10', 'THÁNG ' . sprintf('%d', $currentMonth) . '-' . $currentYear . ' (' . $ngayCuoiThangVN . ')');
    $sheet->getStyle('A10')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FF0000');
    $sheet->getStyle('A10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->mergeCells('A10:I10');

    // Dữ liệu bắt đầu từ dòng 11 (sau dòng tháng)
    $currentRow=11; $stt=1; $sumCols=[3,4,5,6,7,8]; $grandTotals=array_fill_keys($sumCols,0);
    foreach($ships as $ship){
        // Sử dụng hàm mới tinhSoDuTheoThangBaoCao để tính số liệu theo thang_bao_cao
        $soDuData = $dauTonModel->tinhSoDuTheoThangBaoCao($ship, (int)$currentMonth, (int)$currentYear);

        $tonDauKy = (int)$soDuData['ton_dau_ky'];
        $dauCap = (int)$soDuData['dau_cap'];
        $dauSuDungKH = (int)$soDuData['tieu_hao_kh'];
        $dauSuDungCH = (int)$soDuData['tieu_hao_ch'];
        $tongSD = (int)$soDuData['tong_tieu_hao'];
        $tonCuoiKy = (int)$soDuData['ton_cuoi_ky'];

        // Thu thập ghi chú về chuyển dầu (vẫn dùng ngày từ dau_ton.csv)
        $ghiChuParts = [];
        foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
            $ngay = (string)($gd['ngay'] ?? '');
            if ($ngay < $ngayDauThang || $ngay > $ngayCuoiThang) continue;
            $loai = (string)($gd['loai'] ?? '');
            $soLuong = (float)($gd['so_luong_lit'] ?? 0);
            $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));

            if ($loai === 'tinh_chinh' && $transferPairId !== '') {
                // Đây là chuyển dầu
                $lyDo = (string)($gd['ly_do'] ?? '');
                if ($soLuong > 0) {
                    // Nhận dầu từ tàu khác
                    if (preg_match('/(?:nhận từ|nhan tu)\s*([A-Z0-9-]+)/iu', $lyDo, $m)) {
                        $tauNguon = trim($m[1]);
                        $ghiChuParts[] = "nhận " . (int)floor($soLuong) . " lít từ " . $tauNguon;
                    } else {
                        $ghiChuParts[] = "nhận " . (int)floor($soLuong) . " lít từ tàu khác";
                    }
                } else {
                    // Chuyển dầu cho tàu khác
                    if (preg_match('/(?:chuyển sang|chuyen sang)\s*([A-Z0-9-]+)/iu', $lyDo, $m)) {
                        $tauDich = trim($m[1]);
                        $ghiChuParts[] = "chuyển " . (int)floor(abs($soLuong)) . " lít cho " . $tauDich;
                    } else {
                        $ghiChuParts[] = "chuyển " . (int)floor(abs($soLuong)) . " lít cho tàu khác";
                    }
                }
            }
        }

        // Ghi dòng
        $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt++);
        $sheet->setCellValueByColumnAndRow(2,$currentRow,$ship);
        setIntHelper($sheet,3,$currentRow,$tonDauKy);
        setIntHelper($sheet,4,$currentRow,$dauCap);
        setIntHelper($sheet,5,$currentRow,$dauSuDungKH);
        setIntHelper($sheet,6,$currentRow,$dauSuDungCH);
        setIntHelper($sheet,7,$currentRow,$tongSD);
        setIntHelper($sheet,8,$currentRow,$tonCuoiKy);
        // Cột 9 (I): GHI CHÚ - ghi thông tin chuyển dầu nếu có
        $ghiChu = !empty($ghiChuParts) ? implode('; ', $ghiChuParts) : '';
        $sheet->setCellValueByColumnAndRow(9,$currentRow,$ghiChu);
        $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($defaultCellStyle);
        // Căn giữa cho STT, Tên phương tiện
        foreach([1,2] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
        foreach($sumCols as $c){ $grandTotals[$c]+= (int)$sheet->getCellByColumnAndRow($c,$currentRow)->getValue(); }
        $currentRow++;
    }

    // Dòng tổng
    $sheet->setCellValueByColumnAndRow(2,$currentRow,'Tổng');
    foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$grandTotals[$c]); }
    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]]));
    // Căn giữa cho STT, Tên phương tiện
    foreach([1,2] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
    $currentRow++;

    // ========== COPY PHẦN CHỮ KÝ TỪ SHEET2/SHEET3 CỦA TEMPLATE VÀO CUỐI BÁO CÁO ==========
    // Sheet2 (index 1) cho DAUTON-SLCTY, Sheet3 (index 2) cho DAUTON-SLN
    // Xác định sheet index dựa trên suffix
    $footerSheetIndex = ($suffix === 'SLCTY') ? 1 : 2; // Sheet2 cho SLCTY, Sheet3 cho SLN
    $requiredSheetCount = $footerSheetIndex + 1; // Cần ít nhất 2 sheet cho SLCTY, 3 sheet cho SLN
    
    if ($tmpSpreadsheet->getSheetCount() >= $requiredSheetCount) {
        $signatureSheet = $tmpSpreadsheet->getSheet($footerSheetIndex);
        $sigHighestRow = $signatureSheet->getHighestRow();
        $sigHighestCol = $signatureSheet->getHighestColumn();
        $sigHighestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sigHighestCol);

        // Thêm 1 dòng trống trước phần chữ ký
        $currentRow++;
        $sigStartRow = $currentRow; // Lưu dòng bắt đầu copy

        // Copy từng cell từ Sheet2/Sheet3 sang sheet chính
        for ($sigRow = 1; $sigRow <= $sigHighestRow; $sigRow++) {
            for ($sigCol = 1; $sigCol <= $sigHighestColIndex; $sigCol++) {
                $sigColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sigCol);
                $sourceCell = $signatureSheet->getCell($sigColLetter . $sigRow);
                $targetCell = $sheet->getCell($sigColLetter . $currentRow);

                // Copy giá trị
                $targetCell->setValue($sourceCell->getValue());

                // Copy style đầy đủ (bao gồm font, bold, alignment, borders)
                $sourceStyle = $signatureSheet->getStyle($sigColLetter . $sigRow);
                $targetStyle = $sheet->getStyle($sigColLetter . $currentRow);

                // Ép font Times New Roman và in đậm cho toàn bộ nội dung từ Sheet2/Sheet3
                $targetStyle->getFont()
                    ->setName('Times New Roman')
                    ->setSize($sourceStyle->getFont()->getSize() ?: 12)
                    ->setBold(true)
                    ->setItalic($sourceStyle->getFont()->getItalic())
                    ->setUnderline($sourceStyle->getFont()->getUnderline());
                if ($sourceStyle->getFont()->getColor()->getRGB()) {
                    $targetStyle->getFont()->getColor()->setRGB($sourceStyle->getFont()->getColor()->getRGB());
                }

                // Ép căn giữa cho toàn bộ nội dung từ Sheet2/Sheet3
                $targetStyle->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Copy borders nếu có
                $sourceBorders = $sourceStyle->getBorders();
                $targetStyle->getBorders()->applyFromArray([
                    'top' => ['borderStyle' => $sourceBorders->getTop()->getBorderStyle()],
                    'bottom' => ['borderStyle' => $sourceBorders->getBottom()->getBorderStyle()],
                    'left' => ['borderStyle' => $sourceBorders->getLeft()->getBorderStyle()],
                    'right' => ['borderStyle' => $sourceBorders->getRight()->getBorderStyle()],
                ]);
            }
            $currentRow++;
        }

        // Copy merged cells từ Sheet2/Sheet3
        foreach ($signatureSheet->getMergeCells() as $mergeRange) {
            // Parse range để tính toán offset
            preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches);
            if (count($matches) === 5) {
                $startCol = $matches[1];
                $startRow = (int)$matches[2];
                $endCol = $matches[3];
                $endRow = (int)$matches[4];

                // Tính offset dựa trên dòng bắt đầu copy
                $offsetRow = $sigStartRow - 1;
                $newStartRow = $startRow + $offsetRow;
                $newEndRow = $endRow + $offsetRow;

                $newMergeRange = $startCol . $newStartRow . ':' . $endCol . $newEndRow;
                try {
                    $sheet->mergeCells($newMergeRange);
                } catch (\Exception $e) {
                    // Ignore merge errors
                }
            }
        }

        // Copy row heights từ Sheet2/Sheet3
        for ($sigRow = 1; $sigRow <= $sigHighestRow; $sigRow++) {
            $rowHeight = $signatureSheet->getRowDimension($sigRow)->getRowHeight();
            if ($rowHeight > 0) {
                $targetRow = $sigStartRow + $sigRow - 1;
                $sheet->getRowDimension($targetRow)->setRowHeight($rowHeight);
            }
        }
    }

    // ========== THIẾT LẬP PAGE SETUP ĐỂ FOOTER FIT VÀO TRANG A4 DỌC ==========
    $pageSetup = $sheet->getPageSetup();
    $pageSetup->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
    $pageSetup->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

    // Thiết lập margins (tối ưu để có thêm không gian cho footer)
    $pageMargins = $sheet->getPageMargins();
    $pageMargins->setTop(0.75);      // 0.75 inch
    $pageMargins->setRight(0.5);     // 0.5 inch
    $pageMargins->setBottom(0.5);    // 0.5 inch (giảm để có thêm không gian cho footer)
    $pageMargins->setLeft(0.5);      // 0.5 inch
    $pageMargins->setHeader(0.3);   // 0.3 inch
    $pageMargins->setFooter(0.3);   // 0.3 inch

    // Thiết lập fit to page: fit to 1 page width, không giới hạn chiều cao
    // Điều này đảm bảo nội dung fit vào chiều rộng trang, footer sẽ tự động xuống trang tiếp theo nếu cần
    $pageSetup->setFitToWidth(1);
    $pageSetup->setFitToHeight(0); // 0 = không giới hạn số trang theo chiều cao (cho phép nhiều trang nếu cần)

    // Thiết lập print area: từ A1 đến cột I và dòng cuối cùng (bao gồm footer)
    $highestRow = $sheet->getHighestRow();
    $sheet->getPageSetup()->setPrintArea('A1:I' . $highestRow);

    $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);
}

    // Tạo sheet BCTHANG cho mỗi phân loại (xuất mặc định)
    $templatePathBCTHANG = HeaderTemplate::pathFor('BCTHANG');
    if (!$templatePathBCTHANG || !file_exists($templatePathBCTHANG)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathBCTHANG) . '</pre>');
    }
    foreach ($groups as $phanLoai => $rowsInGroup) {
        if (empty($rowsInGroup)) continue;
        $tmpSpreadsheet = IOFactory::load($templatePathBCTHANG);
        $sheet = $tmpSpreadsheet->getSheet(0);
        $suffix = ($phanLoai === 'cong_ty') ? 'SLCTY' : 'SLN';
        $sheetName = 'BCTHANG-' . $suffix;
        $sheet->setTitle($sheetName);
        HeaderTemplate::applyCommonHeader($sheet, 'F4');
        $titleText = 'BẢNG TỔNG HỢP NHIÊN LIỆU VÀ KHỐI LƯỢNG VẬN CHUYỂN HÀNG HÓA THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
        $sheet->setCellValue('A6', $titleText); // Tiêu đề động được ghi vào dòng 6

        // Đánh số trang ở footer (giữa): "Trang X", font Times New Roman
        $sheet->getHeaderFooter()->setOddFooter('&C&"Times New Roman,Regular"&10Trang &P');

        $defaultCellStyle = ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ];

        // Sắp xếp dữ liệu: theo tên tàu -> số chuyến -> ___idx
        usort($rowsInGroup, function($a, $b) {
            // 1. Tên tàu
            $ta = mb_strtolower(trim($a['ten_phuong_tien'] ?? ''));
            $tb = mb_strtolower(trim($b['ten_phuong_tien'] ?? ''));
            if ($ta !== $tb) return $ta <=> $tb;

            // 2. Số chuyến (để insert trip đúng vị trí)
            $tripA = (int)($a['so_chuyen'] ?? 0);
            $tripB = (int)($b['so_chuyen'] ?? 0);
            if ($tripA !== $tripB) return $tripA <=> $tripB;

            // 3. Thứ tự nhập liệu (___idx)
            $idxA = (float)($a['___idx'] ?? 0);
            $idxB = (float)($b['___idx'] ?? 0);
            return $idxA <=> $idxB;
        });

        // Dữ liệu bắt đầu từ dòng 9 vì template đã có header cột ở dòng 7-8
        // sumCols: Các cột cần tính tổng - CỰ LY (G,H,I=7,8,9), KLVC (M=13), SL LUÂN CHUYỂN (N=14), DẦU SD (O=15), phân loại cự ly (V,W,X=22,23,24)
        $currentRow=9; $stt=1; $sumCols=[7,8,9,13,14,15,22,23,24]; $grandTotals=array_fill_keys($sumCols,0); $currentShip=null; $subtotal=array_fill_keys($sumCols,0); $prevTripByShip=[]; $tripSeenByShip=[]; $grandTotalTrips=0; $isFirstRowOfShip=false; $tripCounterForMonth = 0;

        foreach($rowsInGroup as $row){
            $ship=trim($row['ten_phuong_tien']??''); $soChuyen=trim((string)($row['so_chuyen']??'')); $isCapThem=((int)($row['cap_them']??0)===1);
            $isChuyenDau=((int)($row['cap_them']??0)===2);
            
            if($currentShip!==null && $ship!==$currentShip){
                $tripCount=count($tripSeenByShip);
                $sheet->setCellValueByColumnAndRow(3,$currentRow,$currentShip.' Cộng');
                $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
                $sheet->setCellValueByColumnAndRow(5,$currentRow,$tripCount);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
                foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$subtotal[$c]); }
                $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF59D']]]));
                // Căn giữa cho STT, Tên PT, Số ĐK, Số chuyến, Cự ly
                foreach([1,3,4,5,7,8,9] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
                $currentRow++;
                foreach($sumCols as $c){ $grandTotals[$c]+=$subtotal[$c]; }
                $grandTotalTrips+=$tripCount;
                $subtotal=array_fill_keys($sumCols,0);
                $prevTripByShip=[];
                $tripSeenByShip=[];
                $tripCounterForMonth = 0; // Reset số chuyến khi chuyển sang tàu mới
                $stt++; // Tăng STT khi chuyển sang tàu mới
                $isFirstRowOfShip=true;
            }
            // Kiểm tra xem có phải dòng đầu tiên của tàu không
            if($currentShip === null || $currentShip !== $ship) { $isFirstRowOfShip = true; }
            $currentShip=$ship;
            // Ghi STT vào cột A - chỉ hiển thị ở dòng đầu tiên của mỗi tàu
            $sheet->setCellValueByColumnAndRow(1,$currentRow,$isFirstRowOfShip ? $stt : '');
            $isFirstRowOfShip = false; // Các dòng tiếp theo của cùng tàu sẽ không hiển thị STT
            // Cột B (2) để trống - theo template
            $sheet->setCellValueByColumnAndRow(3,$currentRow,$ship); // TÊN PT vào cột C
            // SỐ ĐK vào cột D
            $soDK = $tauModel ? ($tauModel->getSoDangKy($ship) ?: '') : '';
            $sheet->setCellValueByColumnAndRow(4,$currentRow,$soDK);
            // Số chuyến vào cột E - đánh số tuần tự cho mỗi tàu
            // Reset counter khi chuyển sang tàu mới
            if($currentShip === null || $currentShip !== $ship) {
                $tripCounterForMonth = 0;
            }
            // Kiểm tra khối lượng vận chuyển để xác định có hàng hay không
            $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0);
            $showTrip=!isset($prevTripByShip[$soChuyen]) && $soChuyen!=='' && $klvcInt > 0;
            if($showTrip) {
                $tripCounterForMonth++;
                $prevTripByShip[$soChuyen]=true;
            }
            $sheet->setCellValueByColumnAndRow(5,$currentRow,$showTrip?$tripCounterForMonth:'');
            
            // Xử lý cấp thêm dầu
            if($isCapThem){
                $lyDo=trim((string)($row['ly_do_cap_them']??''));
                $litVal=(float)($row['dau_tinh_toan_lit'] ?? ($row['so_luong_cap_them_lit']??0));
                $litInt=toIntHelper($litVal);
                // Hiển thị lý do cấp thêm vào cột TUYẾN ĐƯỜNG (cột F = 6) - chỉ hiển thị lý do, không có prefix
                // Xóa prefix "CẤP THÊM:" nếu có trong $lyDo
                $lyDoClean = preg_replace('/^CẤP THÊM:\s*/i', '', $lyDo);
                $sheet->setCellValueByColumnAndRow(6,$currentRow, $lyDoClean); // TUYẾN ĐƯỜNG cột F (6)
                // Hiển thị ngày cấp thêm vào cột NGÀY ĐI (cột P = 16)
                $ngayCapThem = ''; // Ẩn ngày cho dòng Cấp thêm khi xuất (không ảnh hưởng dữ liệu gốc)
                $sheet->setCellValueByColumnAndRow(16,$currentRow,$ngayCapThem); // NGÀY ĐI cột P (16)
                setIntHelper($sheet,15,$currentRow,$litInt); // DẦU SD cột O (15)
                // Cấp thêm không có cự ly → Mặc định gán vào cột <80km (cột V = 22)
                setIntHelper($sheet,22,$currentRow,$litInt,true); // <80km cột V (22) - hiển thị '-' nếu = 0
                $subtotal[15]+=$litInt; // DẦU SD index (cột O)
                $subtotal[22]+=$litInt; // <80km (cột V)
                $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray($defaultCellStyle);
                // Căn giữa cho STT, Tên PT, Số ĐK, Số chuyến, Cự ly
                foreach([1,3,4,5,7,8,9] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
                $currentRow++;
                continue;
            }

            if($isChuyenDau){
                // Chuyển dầu KHÔNG hiển thị trong BCTHANG
                // - Tàu chuyển đi: chỉ ảnh hưởng tồn cuối kỳ (xử lý trong sheet DAUTON)
                // - Tàu nhận vào: tính vào dầu cấp trong sheet DAUTON
                // => Bỏ qua hoàn toàn, không ghi dòng nào
                continue;
            }

            // TUYẾN ĐƯỜNG cột F: ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
            $route = trim((string)($row['route_hien_thi'] ?? ''));
            if ($route === '') {
                // Fallback: xây dựng tuyến từ các điểm riêng lẻ (chỉ hiển thị 3 điểm)
                $isDoiLenh = (($row['doi_lenh'] ?? '0') == '1');
                $di = trim((string)($row['diem_di'] ?? ''));
                $den = trim((string)($row['diem_den'] ?? ''));
                $b = trim((string)($row['diem_du_kien'] ?? ''));
                if ($isDoiLenh) {
                    $route = $di . ' → ' . $b . ' (đổi lệnh) → ' . $den;
                } else if ($di !== '' || $den !== '') {
                    $route = $di . ' → ' . $den;
                } else {
                    // Chế độ nhập thủ công: không có điểm đi/đến → lấy từ ghi chú (lưu nguyên văn)
                    $route = trim((string)($row['ghi_chu'] ?? ''));
                }
            }
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$route); // TUYẾN ĐƯỜNG cột F
            $val_kh=toIntHelper($row['cu_ly_khong_hang_km']??0); $val_ch=toIntHelper($row['cu_ly_co_hang_km']??0); $tot=$val_kh+$val_ch;
            setIntHelper($sheet,7,$currentRow,$val_kh); setIntHelper($sheet,8,$currentRow,$val_ch); setIntHelper($sheet,9,$currentRow,$tot); // CỰ LY cột G,H,I
            $sheet->setCellValueByColumnAndRow(10,$currentRow,$row['he_so_khong_hang']??''); $sheet->setCellValueByColumnAndRow(11,$currentRow,$row['he_so_co_hang']??''); // HS cột J,K
            $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0); $kllcInt=toIntHelper($row['khoi_luong_luan_chuyen']??0); $fuelInt=toIntHelper($row['dau_tinh_toan_lit']??0);
            // Template có cột L: KL PKTTC (để trống), M: KLVC, N: SL LUÂN CHUYỂN, O: DẦU SD
            $sheet->setCellValueByColumnAndRow(12,$currentRow,''); // KL PKTTC - cột L - để trống
            setIntHelper($sheet,13,$currentRow,$klvcInt); // KLVC vào cột M
            setIntHelper($sheet,14,$currentRow,$kllcInt); // SL LUÂN CHUYỂN vào cột N
            setIntHelper($sheet,15,$currentRow,$fuelInt); // DẦU SD vào cột O
            // Format ngày sang dd/mm/yyyy
            $ngayDi = !empty($row['ngay_di']) ? format_date_vn($row['ngay_di']) : '';
            $ngayDen = !empty($row['ngay_den']) ? format_date_vn($row['ngay_den']) : '';
            $ngayDoXong = !empty($row['ngay_do_xong']) ? format_date_vn($row['ngay_do_xong']) : '';
            $sheet->setCellValueByColumnAndRow(16,$currentRow,$ngayDi); // NGÀY ĐI cột P
            $sheet->setCellValueByColumnAndRow(17,$currentRow,$ngayDen); // NGÀY ĐẾN cột Q
            $sheet->setCellValueByColumnAndRow(18,$currentRow,$ngayDoXong); // NGÀY DỠ XONG cột R
            // Chỉ hiển thị loại hàng khi có hàng (klvcInt > 0), không hàng thì để trống
            $loaiHangValue = ($klvcInt > 0) ? ($row['loai_hang'] ?? '') : '';
            $sheet->setCellValueByColumnAndRow(19,$currentRow,$loaiHangValue); // LOẠI HÀNG cột S
            // Cột T: TÊN TÀU (chỉ dùng trong BC TH, để trống trong BCTHANG)
            $sheet->setCellValueByColumnAndRow(21,$currentRow,$row['ghi_chu']??''); // GHI CHÚ cột U
            $v1=($tot>0 && $tot<80)?$fuelInt:0; $v2=($tot>=80 && $tot<=200)?$fuelInt:0; $v3=($tot>200)?$fuelInt:0;
            setIntHelper($sheet,22,$currentRow,$v1,true); setIntHelper($sheet,23,$currentRow,$v2,true); setIntHelper($sheet,24,$currentRow,$v3,true); // <80 V, 80-200 W, >200 X - hiển thị '-' nếu = 0

            // Cộng dồn Cự ly KH/CH cho tất cả các dòng (theo đúng feedback "Cự ly không hàng em cho cộng xuống nhé")
            // → subtotal[7]: Cự ly KH, subtotal[8]: Cự ly CH
            $subtotal[7] += $val_kh;
            $subtotal[8] += $val_ch;
            // subtotal[9] (Tổng cự ly) được cộng dồn theo từng dòng; về mặt số học = subtotal[7] + subtotal[8]
            $subtotal[9] += $tot;

            // KLVC, SL luân chuyển chỉ cộng một lần cho mỗi chuyến có hàng (giữ nguyên logic cũ)
            $tripKey = $ship.'|'.$soChuyen;
            $isFirstTrip = ($soChuyen!=='' && $klvcInt > 0 && !isset($tripSeenByShip[$tripKey]));
            if($isFirstTrip){
                $subtotal[13] += $klvcInt;
                $subtotal[14] += $kllcInt;
                $tripSeenByShip[$tripKey] = true;
            }

            // Dầu sử dụng & phân loại cự ly vẫn cộng cho từng dòng
            $subtotal[15]+=$fuelInt; $subtotal[22]+=$v1; $subtotal[23]+=$v2; $subtotal[24]+=$v3;
            $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray($defaultCellStyle);
            // Căn giữa cho STT, Tên PT, Số ĐK, Số chuyến, Cự ly
            foreach([1,3,4,5,7,8,9] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
            $currentRow++;
        }
        if($currentShip){
            $tripCount=count($tripSeenByShip);
            $sheet->setCellValueByColumnAndRow(3,$currentRow,$currentShip.' Cộng');
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
            $sheet->setCellValueByColumnAndRow(5,$currentRow,$tripCount);
            $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
            foreach($sumCols as $c){
                $showDash = in_array($c, [22,23,24]); // Chỉ hiển thị '-' cho 3 cột cự ly
                setIntHelper($sheet,$c,$currentRow,$subtotal[$c],$showDash);
                $grandTotals[$c]+=$subtotal[$c];
            }
            $grandTotalTrips+=$tripCount;
            $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF59D']]]));
            // Căn giữa cho STT, Tên PT, Số ĐK, Số chuyến, Cự ly
            foreach([1,3,4,5,7,8,9] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
            $currentRow++;
        }
        $sheet->setCellValueByColumnAndRow(3,$currentRow,'TỔNG');
        $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
        $sheet->setCellValueByColumnAndRow(5,$currentRow,$grandTotalTrips);
        $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
        foreach($sumCols as $c){
            $showDash = in_array($c, [22,23,24]); // Chỉ hiển thị '-' cho 3 cột cự ly
            setIntHelper($sheet,$c,$currentRow,$grandTotals[$c],$showDash);
        }
        $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,[
            'font'=>['bold'=>true],
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]
        ]));
        // Căn giữa cho STT, Tên PT, Số ĐK, Số chuyến, Cự ly
        foreach([1,3,4,5,7,8,9] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
        $currentRow++;

        // ========== COPY PHẦN CHỮ KÝ TỪ SHEET2/SHEET3 CỦA TEMPLATE VÀO CUỐI BÁO CÁO ==========
        // Sheet2 (index 1) cho BCTHANG-SLCTY, Sheet3 (index 2) cho BCTHANG-SLN
        // Xác định sheet index dựa trên suffix
        $footerSheetIndex = ($suffix === 'SLCTY') ? 1 : 2; // Sheet2 cho SLCTY, Sheet3 cho SLN
        $requiredSheetCount = $footerSheetIndex + 1; // Cần ít nhất 2 sheet cho SLCTY, 3 sheet cho SLN
        
        if ($tmpSpreadsheet->getSheetCount() >= $requiredSheetCount) {
            $signatureSheet = $tmpSpreadsheet->getSheet($footerSheetIndex);
            $sigHighestRow = $signatureSheet->getHighestRow();
            $sigHighestCol = $signatureSheet->getHighestColumn();
            $sigHighestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sigHighestCol);

            // Thêm 1 dòng trống trước phần chữ ký
            $currentRow++;
            $sigStartRow = $currentRow; // Lưu dòng bắt đầu copy

            // Copy từng cell từ Sheet2 sang sheet chính
            for ($sigRow = 1; $sigRow <= $sigHighestRow; $sigRow++) {
                for ($sigCol = 1; $sigCol <= $sigHighestColIndex; $sigCol++) {
                    $sigColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sigCol);
                    $sourceCell = $signatureSheet->getCell($sigColLetter . $sigRow);
                    $targetCell = $sheet->getCell($sigColLetter . $currentRow);

                    // Copy giá trị
                    $targetCell->setValue($sourceCell->getValue());

                    // Copy style đầy đủ (bao gồm font, bold, alignment, borders)
                    $sourceStyle = $signatureSheet->getStyle($sigColLetter . $sigRow);
                    $targetStyle = $sheet->getStyle($sigColLetter . $currentRow);

                    // Ép font Times New Roman và in đậm cho toàn bộ nội dung từ Sheet2
                    $targetStyle->getFont()
                        ->setName('Times New Roman')
                        ->setSize($sourceStyle->getFont()->getSize() ?: 12)
                        ->setBold(true)
                        ->setItalic($sourceStyle->getFont()->getItalic())
                        ->setUnderline($sourceStyle->getFont()->getUnderline());
                    if ($sourceStyle->getFont()->getColor()->getRGB()) {
                        $targetStyle->getFont()->getColor()->setRGB($sourceStyle->getFont()->getColor()->getRGB());
                    }

                    // Ép căn giữa cho toàn bộ nội dung từ Sheet2
                    $targetStyle->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    // Copy borders nếu có
                    $sourceBorders = $sourceStyle->getBorders();
                    $targetStyle->getBorders()->applyFromArray([
                        'top' => ['borderStyle' => $sourceBorders->getTop()->getBorderStyle()],
                        'bottom' => ['borderStyle' => $sourceBorders->getBottom()->getBorderStyle()],
                        'left' => ['borderStyle' => $sourceBorders->getLeft()->getBorderStyle()],
                        'right' => ['borderStyle' => $sourceBorders->getRight()->getBorderStyle()],
                    ]);
                }
                $currentRow++;
            }

            // Copy merged cells từ Sheet2
            foreach ($signatureSheet->getMergeCells() as $mergeRange) {
                // Parse range để tính toán offset
                preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches);
                if (count($matches) === 5) {
                    $startCol = $matches[1];
                    $startRow = (int)$matches[2];
                    $endCol = $matches[3];
                    $endRow = (int)$matches[4];

                    // Tính offset dựa trên dòng bắt đầu copy
                    $offsetRow = $sigStartRow - 1;
                    $newStartRow = $startRow + $offsetRow;
                    $newEndRow = $endRow + $offsetRow;

                    $newMergeRange = $startCol . $newStartRow . ':' . $endCol . $newEndRow;
                    try {
                        $sheet->mergeCells($newMergeRange);
                    } catch (\Exception $e) {
                        // Ignore merge errors
                    }
                }
            }

            // Copy row heights từ Sheet2
            for ($sigRow = 1; $sigRow <= $sigHighestRow; $sigRow++) {
                $rowHeight = $signatureSheet->getRowDimension($sigRow)->getRowHeight();
                if ($rowHeight > 0) {
                    $targetRow = $sigStartRow + $sigRow - 1;
                    $sheet->getRowDimension($targetRow)->setRowHeight($rowHeight);
                }
            }
        }

        $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet); $sheetAdded=true;
    }
    if(!$sheetAdded){ if (!headers_sent()) { @header('X-Export-Stop: no_sheets'); } die('<pre style="color:red;font-size:16px;">LỖI: Không xuất được sheet nào! Do dữ liệu rỗng hoặc mapping sai.</pre>'); }

    // ========== TẠO SHEET BC TH (BÁO CÁO TỔNG HỢP THEO TÀU) ==========
    $templatePathBCTH = HeaderTemplate::pathFor('BC_TH');
    if (!$templatePathBCTH || !file_exists($templatePathBCTH)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathBCTH) . '</pre>');
    }
    $tmpSpreadsheet = IOFactory::load($templatePathBCTH);
    $sheet = $tmpSpreadsheet->getSheet(0);
    $sheet->setTitle('BC TH');

    // Tiêu đề chia làm 2 dòng (không có dòng ngày tháng năm)
    // Dòng 5: BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG
    $sheet->setCellValue('A5', 'BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG');
    $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A5:L5');
    // Dòng 6: THÁNG X NĂM XXXX
    $sheet->setCellValue('A6', 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear);
    $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A6:L6');

    // Gom toàn bộ dữ liệu cả công ty và thuê ngoài vào một mảng chung
    $allRows = [];
    foreach ($groups as $rowsInGroup) {
        if (empty($rowsInGroup)) { continue; }
        foreach ($rowsInGroup as $r) { $allRows[] = $r; }
    }
    if (empty($allRows)) {
        // Không có dữ liệu, vẫn thêm sheet trống để nhất quán
        $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);
        return;
    }

    // Nhóm dữ liệu theo tàu
    $shipData = [];
    foreach($allRows as $row){
        $ship=trim($row['ten_phuong_tien']??'');
        if(!isset($shipData[$ship])) $shipData[$ship]=[];
        $shipData[$ship][]=$row;
    }
    ksort($shipData);

    // Thêm dòng "THÁNG XX-YYYY" ở dòng 9 (căn trái)
    $sheet->setCellValue('A9', 'THÁNG ' . sprintf('%02d', $currentMonth) . '-' . $currentYear);
    $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FF0000');
    $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->mergeCells('A9:L9');

    // Dữ liệu bắt đầu từ dòng 10 (sau dòng tháng)
    // Cột BC TH:
    // D = Tổng cự ly (Km)
    // E = KLVC (T)
    // F = SL luân chuyển (T.km)
    // G = Dầu SD không hàng
    // H = Dầu SD có hàng
    // I = Tổng dầu SD
    // J/K/L = Phân loại dầu theo tổng cự ly của chuyến (<80 / 80-200 / >200)
    //
    // Lưu ý nghiệp vụ:
    // - Một chuyến có thể bị tách nhiều dòng (đổi lệnh/___idx). Với BC TH phải tính theo CHUYẾN:
    //   + Tổng cự ly/KLVC/SL luân chuyển: cộng 1 lần/chuyến (chỉ khi chuyến có hàng)
    //   + Dầu SD (KH/CH) & phân loại <80/80-200/>200: cũng tính 1 lần/chuyến để tránh cộng trùng.
    // - Dòng cap_them: cộng vào Dầu SD KH và Tổng dầu, và mặc định vào <80.
    $currentRow=10; $stt=1; $sumCols=[4,5,6,7,8,9,10,11,12]; $grandTotals=array_fill_keys($sumCols,0);

    foreach($shipData as $ship=>$shipRows){
            $subtotal=array_fill_keys($sumCols,0);
            $tripSeen=[];      // chuyến có hàng đã tính (để đếm số chuyến và cộng 1 lần)
            $capThemSeen=[];   // chống cộng trùng cap_them theo ID (phòng trường hợp dữ liệu lặp)
            
            foreach($shipRows as $row){
                $soChuyen=trim((string)($row['so_chuyen']??''));
                $isCapThem=((int)($row['cap_them']??0)===1);
                $isChuyenDau=((int)($row['cap_them']??0)===2);

                // Bỏ qua chuyển dầu - không tính vào dầu sử dụng trong BC TH
                if($isChuyenDau) continue;

                // 1) Dòng cấp thêm: cộng vào DẦU SD KH + TỔNG DẦU, mặc định vào <80
                if($isCapThem){
                    // Chống cộng trùng nếu có id
                    $capId = (string)($row['id'] ?? $row['___idx'] ?? '');
                    if($capId !== '' && isset($capThemSeen[$capId])) { continue; }
                    if($capId !== '') { $capThemSeen[$capId] = true; }

                    $litVal=(float)($row['so_luong_cap_them_lit'] ?? 0);
                    if($litVal <= 0){
                        // fallback: một số dữ liệu cũ có thể lưu vào dau_tinh_toan_lit
                        $litVal=(float)($row['dau_tinh_toan_lit'] ?? 0);
                    }
                    $litInt=toIntHelper($litVal);
                    // Phân loại dầu cho dòng CẤP THÊM theo nghiệp vụ:
                    // - Nếu lý do có chứa "qua cầu" => tính KHÔNG HÀNG
                    // - Ngược lại => tính CÓ HÀNG
                    $lyDoCapThem = mb_strtolower(trim((string)($row['ly_do_cap_them'] ?? '')),'UTF-8');
                    $isQuaCau = ($lyDoCapThem !== '' && mb_strpos($lyDoCapThem, 'qua cầu') !== false) || ($lyDoCapThem !== '' && mb_strpos($lyDoCapThem, 'qua cau') !== false);
                    if ($isQuaCau) {
                        $subtotal[7]+=$litInt; // DẦU SD KH
                    } else {
                        $subtotal[8]+=$litInt; // DẦU SD CH
                    }
                    $subtotal[9]+=$litInt; // TỔNG DẦU SD
                    $subtotal[10]+=$litInt; // <80km
                    continue;
                }

                // 2) Dòng chuyến thường: BC TH phải khớp số liệu với sheet BCTHANG
                // - TỔNG CỰ LY (BC TH) = tổng cột "TỔNG CỰ LY" (G+H) của BCTHANG cho tàu đó (cộng theo TỪNG DÒNG)
                // - DẦU SD KH (BC TH) = tổng DẦU SD (Lít) của các dòng BCTHANG mà "KLVC (T)" trống/0
                // - DẦU SD CH (BC TH) = tổng DẦU SD (Lít) của các dòng BCTHANG mà "KLVC (T)" > 0
                // - KLVC / SL luân chuyển và SỐ CHUYẾN: vẫn chỉ cộng/đếm 1 lần cho mỗi chuyến có hàng (tránh trùng do đổi lệnh)
                $tripKey=$ship.'|'.$soChuyen;

                $valKh=toIntHelper($row['cu_ly_khong_hang_km']??0);
                $valCh=toIntHelper($row['cu_ly_co_hang_km']??0);
                $tot=$valKh+$valCh;

                // Tổng cự ly: cộng đúng theo cột TỔNG CỰ LY của BCTHANG (từng dòng)
                $subtotal[4]+=$tot;

                // Dầu SD theo từng dòng (giống BCTHANG cột O)
                $fuelInt=toIntHelper($row['dau_tinh_toan_lit']??0);

                // Phân loại KH/CH theo điều kiện "ở dòng đó không có KLVC (T)" (tức klvc == 0)
                // (không dựa theo khoi_luong_van_chuyen_t vì thực tế BCTHANG có thể để trống KLVC khi không hàng)
                $klvcLine=toIntHelper($row['khoi_luong_van_chuyen_t']??0);
                if($klvcLine <= 0){
                    $subtotal[7]+=$fuelInt; // KH
                } else {
                    $subtotal[8]+=$fuelInt; // CH
                }
                $subtotal[9]+=$fuelInt;

                // Phân loại theo tổng cự ly của DÒNG (như BCTHANG đang phân loại)
                $v1=($tot>0 && $tot<80)?$fuelInt:0;
                $v2=($tot>=80 && $tot<=200)?$fuelInt:0;
                $v3=($tot>200)?$fuelInt:0;
                $subtotal[10]+=$v1; $subtotal[11]+=$v2; $subtotal[12]+=$v3;

                // Chỉ đếm/tính chuyến có hàng 1 lần
                $klvc=(float)($row['khoi_luong_van_chuyen_t']??0);
                $isFirstTrip=($soChuyen!=='' && $klvc > 0 && !isset($tripSeen[$tripKey]));
                if($isFirstTrip){
                    $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0);
                    $kllcInt=toIntHelper($row['khoi_luong_luan_chuyen']??0);
                    $subtotal[5]+=$klvcInt;
                    $subtotal[6]+=$kllcInt;
                    $tripSeen[$tripKey]=true;
                }
            }

            // Ghi dòng tổng cho tàu
            // A=STT, B=PHƯƠNG TIỆN, C=SỐ CHUYẾN, D=TỔNG CỰ LY, E=KLVC, F=SL LUÂN CHUYỂN, G-H=DẦU SD (KH/CH), I=TỔNG DẦU SD, J-L=<80/80-200/>200
            $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt++);
            $sheet->setCellValueByColumnAndRow(2,$currentRow,$ship);
            $sheet->setCellValueByColumnAndRow(3,$currentRow,count($tripSeen));
            setIntHelper($sheet,4,$currentRow,$subtotal[4]); // TỔNG CỰ LY
            setIntHelper($sheet,5,$currentRow,$subtotal[5]); // KLVC
            setIntHelper($sheet,6,$currentRow,$subtotal[6]); // SL LUÂN CHUYỂN
            setIntHelper($sheet,7,$currentRow,$subtotal[7]); // DẦU SD KH
            setIntHelper($sheet,8,$currentRow,$subtotal[8]); // DẦU SD CH
            setIntHelper($sheet,9,$currentRow,$subtotal[9]); // TỔNG DẦU SD
            setIntHelper($sheet,10,$currentRow,$subtotal[10]); // <80
            setIntHelper($sheet,11,$currentRow,$subtotal[11]); // 80-200
            setIntHelper($sheet,12,$currentRow,$subtotal[12]); // >200
            $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray($defaultCellStyle);
            // Căn giữa cho STT, Phương tiện, Số chuyến, Tổng cự ly
            foreach([1,2,3,4] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }

            foreach($sumCols as $c){ $grandTotals[$c]+=$subtotal[$c]; }
            $currentRow++;
        }
        
    // Dòng tổng
    // Tính tổng số chuyến - CHỈ đếm chuyến CÓ HÀNG (klvc > 0)
    $totalTrips = 0;
    foreach($shipData as $ship=>$shipRows){
        $tripSeen=[];
        foreach($shipRows as $row){
            $soChuyen=trim((string)($row['so_chuyen']??''));
            $isCapThem=((int)($row['cap_them']??0)===1);
            $klvc=(float)($row['khoi_luong_van_chuyen_t']??0);
            $tripKey=$ship.'|'.$soChuyen;
            // Chỉ đếm chuyến có hàng (klvc > 0), không đếm cấp thêm
            if($soChuyen!=='' && !$isCapThem && $klvc > 0 && !isset($tripSeen[$tripKey])){ $tripSeen[$tripKey]=true; }
        }
        $totalTrips += count($tripSeen);
    }

    $sheet->setCellValueByColumnAndRow(2,$currentRow,'Tổng cộng:');
    $sheet->setCellValueByColumnAndRow(3,$currentRow,$totalTrips);
    foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$grandTotals[$c]); }
    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]]));
    // Căn giữa cho STT, Phương tiện, Số chuyến, Tổng cự ly
    foreach([1,2,3,4] as $col){ $sheet->getStyleByColumnAndRow($col,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }

    $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);

    // ========== TẠO SHEET DAUTON-SLCTY VÀ DAUTON-SLN ==========
    $templatePathDAUTON = HeaderTemplate::pathFor('DAUTON');
    if (!$templatePathDAUTON || !file_exists($templatePathDAUTON)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathDAUTON) . '</pre>');
    }
    foreach ($groups as $phanLoai => $rowsInGroup) {
        if (empty($rowsInGroup)) continue;
        $suffix = ($phanLoai === 'cong_ty') ? 'SLCTY' : 'SLN';
        createDAUTONSheet($spreadsheet, $templatePathDAUTON, $rowsInGroup, $currentMonth, $currentYear, $suffix, $tauModel, $dauTonModel, $ketQuaModel, ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ]);
    }

    // Stream workbook once after adding all sheets
    $spreadsheet->setActiveSheetIndex(0);
    if(function_exists('ob_get_level')){ while(ob_get_level()>0){ @ob_end_clean(); } }
    @error_reporting(0);
    $filename = ($isDetailedExport ? 'CT_T' : 'BCTHANG_T') . $currentMonth . '_' . $currentYear . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0'); header('Pragma: public');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); $writer->save('php://output');
    $spreadsheet->disconnectWorksheets(); unset($spreadsheet); exit();
}
