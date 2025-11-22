<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eClinic Admin - Patient Records</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    @media print {
      @page {
        size: A4 landscape;
        margin: 10mm;
      }
      body * {
        visibility: hidden !important;
      }
      .print-table, .print-table *,
      .print-heading {
        visibility: visible !important;
      }
      .print-table {
        position: absolute !important;
        left: 0;
        top: 60px;
        width: 100vw;
        background: white;
        box-shadow: none;
        margin: 0;
        padding: 0;
        font-size: 10px !important;
      }
      .print-table th, .print-table td {
        padding: 2px 4px !important;
        font-size: 10px !important;
        word-break: break-word;
        max-width: 120px;
      }
      .print-heading {
        position: absolute !important;
        left: 0;
        top: 0;
        width: 100vw;
        text-align: center;
        background: white;
        font-size: 1.2rem !important;
        font-weight: bold;
        color: #0ea5e9;
        margin: 0;
        padding: 10px 0 5px 0;
      }
    }
  </style>
</head>

<body class="bg-gray-100 font-sans">
<div class="flex">

  <!-- Sidebar -->
  <aside class="w-80 bg-sky-400 min-h-screen fixed flex flex-col justify-between px-6 py-8 border-r-4 border-indigo-200">
    <div>
      <div class="flex justify-center mb-6">
        <img src="<?= base_url() ?>/public/pic.img/logo_with__text-removebg-preview.png" alt="eClinic Logo" class="h-40 w-40 object-contain">
      </div>
      <nav class="space-y-6">
        <a href="<?= site_url('admin/dashboard') ?>" class="text-white font-semibold text-lg block rounded hover:bg-white/20 px-3 py-2">Admin Dashboard</a>
        <a href="<?= site_url('admin/appointments') ?>" class="text-white font-semibold text-lg block rounded hover:bg-white/20 px-3 py-2">Appointment Management</a>
        <a href="<?= site_url('admin/findings') ?>" class="text-white font-semibold text-lg block rounded hover:bg-white/20 px-3 py-2">Findings & Prescription</a>
        <a href="<?= site_url('admin/records') ?>" class="text-white font-semibold text-lg block rounded hover:bg-white/20 px-3 py-2">Patients Records</a>
      </nav>
    </div>

    <div class="mb-2">
      <a href="<?= site_url('auth/logout') ?>" 
         class="bg-white text-sky-400 text-lg font-bold py-2 px-20 rounded-full w-full hover:bg-sky-50 block text-center">
        Log Out
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="ml-80 flex-1 p-10">
    <h1 class="text-4xl font-bold text-sky-400 mb-8 text-center print-heading">Patient Records</h1>

    <!-- Search & Filter Card -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6 max-w-6xl mx-auto">
      <form id="filtersForm" method="GET" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-between gap-4">

        <!-- Search -->
        <div class="flex w-full sm:w-1/3">
          <input 
            type="text" 
            name="search" 
            placeholder="Search by ID or Full Name..." 
            value="<?= htmlspecialchars($search ?? '') ?>" 
            class="px-4 py-2 w-full rounded-l-md border-2 border-gray-300 focus:ring-2 focus:ring-sky-400"
          >
          <button type="submit" 
            class="px-6 py-2 bg-sky-400 text-white rounded-r-md hover:bg-sky-500 transition">
            Search
          </button>
        </div>

        <!-- Month Filter -->
        <div class="flex items-center space-x-2">
          <label for="month" class="text-gray-700 font-semibold">Month:</label>
          <select name="month" id="month" class="border-2 border-gray-300 rounded-md px-5 py-2">
            <option value="">All</option>
            <?php 
              $months = [
                '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
                '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
                '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
              ];
              foreach ($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= (isset($_GET['month']) && $_GET['month'] == $num) ? 'selected' : '' ?>>
                <?= $name ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="flex items-center space-x-2">
          <label for="status" class="text-gray-700 font-semibold">Status:</label>
          <select name="status" id="status" class="border-2 border-gray-300 rounded-md px-5 py-2">
            <option value="">All</option>
            <option value="Pending" <?= ($_GET['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Accepted" <?= ($_GET['status'] ?? '') == 'Accepted' ? 'selected' : '' ?>>Accepted</option>
            <option value="Completed" <?= ($_GET['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= ($_GET['status'] ?? '') == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <!-- Buttons -->
        <div class="flex gap-2 items-center">
          <button type="submit" 
            class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
            Apply Filter
          </button>

          <!-- Clean Print Button -->
          <button type="button"
            onclick="window.print()" 
            class="px-4 py-2 bg-sky-400 text-white rounded-md hover:bg-sky-500 transition flex items-center justify-center"
            title="Print Records">
            🖨️
          </button>
        </div>

      </form>
    </div>

    <!-- Patient Records Table -->
    <div class="bg-white shadow-md rounded-lg p-6 max-w-6xl mx-auto overflow-x-auto print-table">
      <table class="min-w-full table-auto border-collapse text-left">
        <thead class="bg-sky-400 text-white">
          <tr>
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Full Name</th>
            <th class="px-4 py-3">Age</th>
            <th class="px-4 py-3">Contact</th>
            <th class="px-4 py-3">Appointment Date</th>
            <th class="px-4 py-3">Purpose</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Findings</th>
            <th class="px-4 py-3">Prescription</th>
          </tr>
        </thead>

        <tbody class="text-gray-700">
          <?php if (!empty($records)): ?>
            <?php foreach ($records as $row): ?>
              
              <?php
                $status = strtolower(trim($row['status'] ?? 'pending'));
                if (!empty($row['findings']) && !empty($row['prescription'])) {
                    $status = 'completed';
                }

                $class = match($status) {
                    'accepted','completed' => 'text-green-600',
                    'pending' => 'text-yellow-500',
                    'cancelled' => 'text-red-500',
                    default => 'text-gray-500'
                };
              ?>

              <tr class="border-t border-gray-200 hover:bg-gray-50">
                <td class="px-6 py-4"><?= htmlspecialchars($row['user_id']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['fullname']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['age']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['contact']) ?></td>
                <td class="px-6 py-4"><?= date("F j, Y", strtotime($row['appointment_date'])) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['purpose']) ?></td>
                <td class="px-6 py-4"><span class="<?= $class ?> font-medium"><?= ucfirst($status) ?></span></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['findings'] ?: '-') ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['prescription'] ?: '-') ?></td>
              </tr>

            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="px-6 py-4 text-center text-gray-500">No patient records found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($page)): ?>
        <div class="mt-6 flex justify-center"><?= $page ?></div>
      <?php endif; ?>
    </div>

  </main>
</div>

</body>
</html>
