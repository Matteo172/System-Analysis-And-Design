<?php
// ============================================================
//  Barangay Tugtug E-System — Track Request
//  File: php/track_request.php
//  GET ?ref=BRGY-2026-1234  or  ?ref=DOC-2026-1234
// ============================================================
header("Content-Type: application/json");
ini_set("display_errors", 0);
ini_set("log_errors", 1);
 
define("DB_HOST",    "localhost");
define("DB_NAME",    "db_barangay_e-system");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");
 
$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
$opt = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false];
 
try { $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt); }
catch (PDOException $e) {
    echo json_encode(["success"=>false,"message"=>"Database connection error."]);
    exit;
}
 
$ref = isset($_GET["ref"]) ? trim($_GET["ref"]) : "";
 
if (empty($ref)) {
    echo json_encode(["success"=>false,"message"=>"No reference number provided."]);
    exit;
}
 
// ── Determine type by prefix ──────────────────────────────────
if (str_starts_with($ref, "BRGY-")) {
    // Blotter lookup
    try {
        $stmt = $pdo->prepare(
            "SELECT b.blotter_ID, b.name, b.complainant_name,
                    b.incident_date, b.status, b.complaint_details,
                    br.blotter_refnumber
             FROM blotter_reference_number br
             JOIN blotter b ON br.blotter_ID = b.blotter_ID
             WHERE br.blotter_refnumber = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();
 
        if (!$row) {
            echo json_encode(["success"=>false,"message"=>"Reference number not found."]);
            exit;
        }
 
        echo json_encode([
            "success" => true,
            "type"    => "blotter",
            "data"    => [
                "reference_number" => $row["blotter_refnumber"],
                "name"             => $row["name"],
                "complainant"      => $row["complainant_name"],
                "incident_date"    => $row["incident_date"],
                "complaint"        => $row["complaint_details"],
                "status"           => $row["status"],
                "price"            => "Free",
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(["success"=>false,"message"=>"Query error."]);
    }
 
} elseif (str_starts_with($ref, "DOC-")) {
    // Document lookup
    // Price list per document type
    $price_list = [
        1 => ["name" => "Barangay Clearance",       "price" => "₱50.00"],
        2 => ["name" => "Certificate of Indigency", "price" => "Free"],
        3 => ["name" => "Certificate of Residency", "price" => "₱50.00"],
        4 => ["name" => "Business Permit",           "price" => "₱100.00"],
    ];
 
    try {
        $stmt = $pdo->prepare(
            "SELECT dr.request_ID, dr.resident_ID, ri.first_name, ri.last_name,
                    dr.document_ID, dr.document_purpose, dr.date, dr.status,
                    dr.date_released, dr.quantity, dn.document_refnumber
             FROM document_reference_number dn
             JOIN document_request dr ON dn.request_ID = dr.request_ID
             JOIN resident_information ri ON dr.resident_ID = ri.resident_ID
             WHERE dn.document_refnumber = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();
 
        if (!$row) {
            echo json_encode(["success"=>false,"message"=>"Reference number not found."]);
            exit;
        }
 
        $doc_id   = $row["document_ID"] ?? 1;
        $doc_info = $price_list[$doc_id] ?? ["name"=>"Document","price"=>"₱50.00"];
 
        echo json_encode([
            "success" => true,
            "type"    => "document",
            "data"    => [
                "reference_number" => $row["document_refnumber"],
                "name"             => $row["first_name"] . " " . $row["last_name"],
                "document_type"    => $doc_info["name"],
                "purpose"          => $row["document_purpose"],
                "date_requested"   => $row["date"],
                "date_released"    => $row["date_released"],
                "quantity"         => $row["quantity"],
                "status"           => $row["status"],
                "price"            => $doc_info["price"],
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(["success"=>false,"message"=>"Query error."]);
    }
 
} else {
    echo json_encode(["success"=>false,"message"=>"Invalid reference number format."]);
}
exit;
?>