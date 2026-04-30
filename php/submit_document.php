<?php
// ============================================================
//  Barangay Tugtug E-System — Submit Document Request
//  File: php/submit_document.php
// ============================================================
header("Content-Type: application/json");
ini_set("display_errors", 0);
ini_set("log_errors", 1);
error_reporting(E_ALL);

define("DB_HOST", "localhost");
define("DB_NAME", "db_barangay_e-system");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_CHARSET", "utf8mb4");

$dsn =
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database connection error.",
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$body = file_get_contents("php://input");
$data = json_decode($body, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid data."]);
    exit();
}

// Generate reference number
$year = date("Y");
$random = str_pad(mt_rand(1000, 9999), 4, "0", STR_PAD_LEFT);
$ref_num = "DOC-" . $year . "-" . $random;

// Ensure uniqueness
try {
    $check = $pdo->prepare(
        "SELECT document_refnumber FROM document_reference_number WHERE document_refnumber = :ref",
    );
    $check->execute([":ref" => $ref_num]);
    while ($check->fetch()) {
        $random = str_pad(mt_rand(1000, 9999), 4, "0", STR_PAD_LEFT);
        $ref_num = "DOC-" . $year . "-" . $random;
        $check->execute([":ref" => $ref_num]);
    }
} catch (PDOException $e) {
    /* continue */
}

try {
    // Insert or find resident
    $res_check = $pdo->prepare(
        "SELECT resident_ID FROM resident_information
         WHERE first_name=:fn AND last_name=:ln LIMIT 1",
    );
    $res_check->execute([
        ":fn" => $data["first_name"] ?? "",
        ":ln" => $data["last_name"] ?? "",
    ]);
    $resident = $res_check->fetch();

    if ($resident) {
        $resident_id = $resident["resident_ID"];
    } else {
        $ins = $pdo->prepare(
            "INSERT INTO resident_information
                (first_name, last_name, middle_initial, sex, birthdate, birthplace)
             VALUES (:fn, :ln, :mi, :sex, :bd, :bp)",
        );
        $middle = $data["middle_name"] ?? "";
        $mi = $middle ? strtoupper(substr($middle, 0, 1)) : "";
        $gender = $data["gender"] ?? "";
        $sex = $gender === "Male" ? "M" : ($gender === "Female" ? "F" : "O");
        $ins->execute([
            ":fn" => $data["first_name"] ?? "",
            ":ln" => $data["last_name"] ?? "",
            ":mi" => $mi,
            ":sex" => $sex,
            ":bd" => $data["birthday"] ?? null,
            ":bp" => $data["birthplace"] ?? "",
        ]);
        $resident_id = $pdo->lastInsertId();
    }

    // Map certificate to document_ID
    $cert_map = [
        "Barangay Clearance" => 1,
        "Certificate of Indigency" => 2,
        "Certificate of Residency" => 3,
        "Business Permit" => 4,
    ];
    $doc_id = $cert_map[$data["certificate"] ?? ""] ?? 1;

    // Insert document request
    $doc_stmt = $pdo->prepare(
        "INSERT INTO document_request
                (document_refnumber, resident_ID, document_ID, contact, document_purpose,
                 date, status, age, length_stay_years, length_stay_months, quantity)
             VALUES (:ref, :rid, :did, :contact, :purpose,
                 CURDATE(), 'Pending', :age, :stay_y, :stay_m, :qty)",
    );

    $doc_stmt->execute([
        ":ref" => $ref_num, // Added reference number here
        ":rid" => $resident_id,
        ":did" => $doc_id,
        ":contact" => $data["contact"] ?? "",
        ":purpose" => $data["purpose"] ?? "",
        ":age" => $data["age"] ?? 0,
        ":stay_y" => $data["stay_years"] ?? 0,
        ":stay_m" => $data["stay_months"] ?? 0,
        ":qty" => $data["quantity"] ?? 1,
    ]);
    $request_id = $pdo->lastInsertId();

    // Insert reference number to the secondary table (as per your original script)
    $ref_stmt = $pdo->prepare(
        "INSERT INTO document_reference_number (document_refnumber, request_ID)
             VALUES (:ref, :req_id)",
    );
    $ref_stmt->execute([":ref" => $ref_num, ":req_id" => $request_id]);

    echo json_encode([
        "success" => true,
        "reference_number" => $ref_num,
        "request_id" => $request_id,
    ]);
} catch (PDOException $e) {
    error_log("Insert error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Failed to save request. " . $e->getMessage(),
    ]);
}
exit();
?>