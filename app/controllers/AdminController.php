<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AdminController extends Controller
{
    protected $AppointmentModel, $RecordModel, $UserModel;

    public function __construct()
    {
        parent::__construct();

        $this->AppointmentModel = new AppointmentModel();
        $this->RecordModel = new RecordModel();
        $this->UserModel = new UserModel();

        // Restrict access to admin only
        if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
            redirect('auth/login');
            exit;
        }
    }

    // ==============================================
    // ADMIN DASHBOARD
    // ==============================================
    public function dashboard()
    {
        $appointments = $this->AppointmentModel->getAllAppointments();
        $users = $this->UserModel->getAllUsers();

        $totalAppointments = count($appointments);
        $totalPatients = count(array_filter($users, fn($u) => $u['role'] === 'user'));

        $today = date('Y-m-d');
        $todayAppointmentsList = array_filter($appointments, fn($a) =>
            $a['appointment_date'] === $today && $a['status'] === 'Accepted'
        );
        $todaysAppointments = count($todayAppointmentsList);

        $monthlyAppointments = array_fill(0, 12, 0);
        foreach ($appointments as $appt) {
            $month = (int)date('n', strtotime($appt['appointment_date'])) - 1;
            $monthlyAppointments[$month]++;
        }

        $this->call->view('admin/dashboard', [
            'appointments' => $appointments,
            'users' => $users,
            'totalAppointments' => $totalAppointments,
            'totalPatients' => $totalPatients,
            'todaysAppointments' => $todaysAppointments,
            'todayAppointmentsList' => $todayAppointmentsList,
            'monthlyAppointments' => $monthlyAppointments
        ]);
    }

    // ==============================================
    // MANAGE PENDING APPOINTMENTS
    // ==============================================
    public function appointments()
    {
        $appointments = $this->AppointmentModel->getAppointmentsByStatus('Pending');

        if (isset($_GET['accept_id'])) {
            $id = (int)$_GET['accept_id'];
            $this->AppointmentModel->updateStatusAndSync($id, 'Accepted');
            redirect('admin/appointments');
            exit;
        }

        if (isset($_GET['cancel_id'])) {
            $id = (int)$_GET['cancel_id'];
            $this->AppointmentModel->updateStatusAndSync($id, 'Cancelled');
            redirect('admin/appointments');
            exit;
        }

        $this->call->view('admin/appointments', ['appointments' => $appointments]);
    }

    // ==============================================
    // FINDINGS & PRESCRIPTION MODULE
    // ==============================================
    public function findings()
    {
        $successMessage = '';
        $errorMessage = '';
        $findings = '';
        $prescription = '';

        $acceptedAppointments = $this->AppointmentModel->getAppointmentsByStatus('Accepted');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $appointment_id = $_POST['appointment_id'] ?? null;
            $findings = trim($_POST['findings'] ?? '');
            $prescription = trim($_POST['prescription'] ?? '');

            if ($appointment_id && $findings && $prescription) {
                // ✅ Get user_id & appointment_date from appointment table
                $appt = $this->AppointmentModel->getAppointmentById($appointment_id);
                if ($appt) {
                    $saveResult = $this->RecordModel->updateFindingsPrescription(
                        $appt['user_id'],
                        $appt['appointment_date'],
                        $findings,
                        $prescription
                    );

                    if ($saveResult) {
                        $this->AppointmentModel->updateStatusAndSync($appointment_id, 'Completed');
                        $successMessage = '✅ Findings and prescription saved successfully.';
                        $acceptedAppointments = array_filter($acceptedAppointments, fn($a) => $a['id'] != $appointment_id);
                        $findings = '';
                        $prescription = '';
                    } else {
                        $errorMessage = '⚠️ Error saving findings/prescription. Please try again.';
                    }
                } else {
                    $errorMessage = '⚠️ Appointment not found.';
                }
            } else {
                $errorMessage = '⚠️ All fields are required.';
            }
        }

        $this->call->view('admin/findings', [
            'acceptedAppointments' => $acceptedAppointments,
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
            'findings' => $findings,
            'prescription' => $prescription
        ]);
    }

    // ==============================================
    // QUICK ACTION: ACCEPT OR CANCEL
    // ==============================================
    public function manageAppointments()
    {
        if (isset($_GET['accept_id'])) {
            $id = (int)$_GET['accept_id'];
            $this->AppointmentModel->updateStatusAndSync($id, 'Accepted');
        }

        if (isset($_GET['cancel_id'])) {
            $id = (int)$_GET['cancel_id'];
            $this->AppointmentModel->updateStatusAndSync($id, 'Cancelled');
        }

        redirect('admin/appointments');
    }

    // ==============================================
    // PATIENT RECORDS PAGE
    // ==============================================
public function records()
{
    $search = trim($_GET['search'] ?? '');
    $month  = trim($_GET['month'] ?? '');
    $status = trim($_GET['status'] ?? '');

    // ✅ Main query with grouped subquery for findings/prescription
    $sql = "
        SELECT 
            a.id AS appointment_id,
            a.user_id,
            pi.full_name AS fullname,
            pi.age,
            pi.phone AS contact,
            a.appointment_date,
            a.purpose,
            a.status,
            COALESCE(pr.findings, '') AS findings,
            COALESCE(pr.prescription, '') AS prescription
        FROM appointments a
        JOIN personal_information pi 
            ON a.user_id = pi.user_id
        LEFT JOIN (
            SELECT 
                user_id,
                DATE(appointment_date) AS appt_date,
                MAX(findings) AS findings,
                MAX(prescription) AS prescription
            FROM patient_records
            GROUP BY user_id, DATE(appointment_date)
        ) pr 
            ON pr.user_id = a.user_id 
           AND DATE(a.appointment_date) = pr.appt_date
        WHERE 1=1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (pi.full_name LIKE :search OR a.user_id LIKE :search_id)";
        $params[':search'] = "%{$search}%";
        $params[':search_id'] = "%{$search}%";
    }

    if ($month !== '') {
        $m = (int)$month;
        if ($m >= 1 && $m <= 12) {
            $sql .= " AND MONTH(a.appointment_date) = :month";
            $params[':month'] = $m;
        }
    }

    if ($status !== '') {
        $sql .= " AND a.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY a.appointment_date DESC";

    try {
        $stmt = $this->db->raw($sql, $params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        throw $e;
    }

    $this->call->view('admin/records', [
        'records' => $records,
        'search'  => $search,
        'month'   => $month,
        'status'  => $status
    ]);
}
    // ==============================================
    // ADD FINDINGS & PRESCRIPTION (MANUAL ENTRY)
    // ==============================================
    public function addFindingsPrescription()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'user_id'          => $_POST['user_id'] ?? null,
                'fullname'         => $_POST['fullname'] ?? '',
                'age'              => $_POST['age'] ?? 0,
                'contact'          => $_POST['contact'] ?? '',
                'appointment_date' => $_POST['appointment_date'] ?? '',
                'purpose'          => $_POST['purpose'] ?? '',
                'findings'         => trim($_POST['findings'] ?? ''),
                'prescription'     => trim($_POST['prescription'] ?? '')
            ];

            if ($data['user_id'] && $data['findings'] && $data['prescription']) {
                $saveResult = $this->RecordModel->addFindingsPrescriptionManual($data);

                if ($saveResult) {
                    redirect('admin/records');
                } else {
                    echo "⚠️ Failed to save findings/prescription manually.";
                }
            } else {
                echo "⚠️ All fields are required for manual entry.";
            }
        }
    }
            public function printAllAppointments()
        {
            $appointments = $this->AppointmentModel->getAllAppointments();
            $this->call->view('admin/appointments', [
                'appointments' => $appointments,
                'printMode' => true // flag for print mode in view
            ]);
        }
}
?>
