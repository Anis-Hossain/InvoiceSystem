<?php
require_once 'includes/config.php';
requireLogin();
header('Content-Type: application/json');

$scope = isAdmin() ? '' : (empty(getUserClientIds()) ? ' AND 1=0' : ' AND client_id IN (' . implode(',', array_map('intval', getUserClientIds())) . ')');
$scope_join = clientScope('i');

$data = [
 'invoices' => (int)$conn->query("SELECT COUNT(*) c FROM invoices WHERE 1=1 $scope")->fetch_assoc()['c'],
 'revenue' => money($conn->query("SELECT COALESCE(SUM(p.amount),0) s FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE 1=1 $scope_join")->fetch_assoc()['s']),
 'pending' => money($conn->query("SELECT COALESCE(SUM(total),0) s FROM invoices WHERE status IN ('sent','overdue') $scope")->fetch_assoc()['s']),
 'overdue' => (int)$conn->query("SELECT COUNT(*) c FROM invoices WHERE status='overdue' $scope")->fetch_assoc()['c'],
 // Admin sees total registered clients; user sees only their linked clients
 'clients' => isAdmin()
    ? (int)$conn->query("SELECT COUNT(*) c FROM clients")->fetch_assoc()['c']
    : (int)$conn->query("SELECT COUNT(*) c FROM user_clients WHERE user_id = " . (int)$_SESSION['user_id'])->fetch_assoc()['c']
];

echo json_encode($data);
