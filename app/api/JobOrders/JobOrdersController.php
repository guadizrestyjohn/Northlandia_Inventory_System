<?php

namespace Api\JobOrders;

use Api\BaseApiController;
use Core\Auth;
use Core\Validation;
use Models\JobOrders;
use Models\Orders;

class JobOrdersController extends BaseApiController
{
    private $JobOrders;
    private $Orders;

    public function __construct()
    {
        $this->JobOrders = new JobOrders();
        $this->Orders = new Orders();
    }

    public function all(): void
    {
        $this->requirePermission('job_orders.view');
        $this->requireGet();
        $user = Auth::user() ?? [];
        $productionAssignment = ($user['role'] ?? '') === 'Production Staff'
            ? 'Production Staff'
            : null;
        $this->respond(['success' => true, 'data' => $this->JobOrders->find($productionAssignment)]);
    }

    public function add(): void
    {
        $this->requirePermission('job_orders.create');
        $this->requirePost();
        $orderId = trim($_POST['order_id'] ?? '');
        if (!ctype_digit($orderId) || !$this->Orders->findByID($orderId)) {
            $this->respond(['success' => false, 'message' => 'Please select a valid order.'], 422);
        }
        $result = $this->JobOrders->create($orderId);
        $this->respond($result, !empty($result['success']) ? 200 : 422);
    }

    public function approval(): void
    {
        $this->requirePermission('job_orders.approval');
        $this->requirePost();
        $id = trim($_POST['job_order_id'] ?? '');
        $approval = trim($_POST['customer_approval'] ?? '');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->respond(['success' => false, 'message' => 'Job Order not found.'], 404);
        }
        if (!in_array($approval, ['Pending', 'Approved', 'Rejected'], true)) {
            $this->respond(['success' => false, 'message' => 'Invalid customer approval.'], 422);
        }
        if ($job->status !== 'Pending') {
            $this->respond(['success' => false, 'message' => 'Customer approval cannot be changed after production has started.'], 409);
        }
        $success = $this->JobOrders->updateCustomerApproval($id, $approval);
        $this->respond(['success' => $success, 'message' => $success ? 'Customer approval updated successfully.' : 'Customer approval could not be updated. The Job Order may have progressed.'], $success ? 200 : 409);
    }

    public function endorse(): void
    {
        $this->requirePermission('job_orders.endorse');
        $this->requirePost();
        $id = trim($_POST['job_order_id'] ?? '');
        $assignment = trim($_POST['production_assignment'] ?? '');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->respond(['success' => false, 'message' => 'Job Order not found.'], 404);
        }
        if ($assignment === '') {
            $this->respond(['success' => false, 'message' => 'Production assignment is required.'], 422);
        }
        if ($job->customer_approval !== 'Approved') {
            $this->respond(['success' => false, 'message' => 'Customer approval is required before production.'], 422);
        }
        $success = $this->JobOrders->endorseToProduction($id, $assignment);
        $this->respond(['success' => $success, 'message' => $success ? 'Job Order endorsed to production successfully.' : 'Job Order could not be endorsed. Check its current status and approval.'], $success ? 200 : 409);
    }

    public function timeframe(): void
    {
        $this->requirePermission('production.timeframe');
        $this->requirePost();
        $id = trim($_POST['job_order_id'] ?? '');
        $endDate = trim($_POST['production_end_date'] ?? '');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->respond(['success' => false, 'message' => 'Job Order not found.'], 404);
        }
        $error = Validation::productionEndDate($endDate, $job->production_start_date ? (string) $job->production_start_date : null);
        if ($error !== null) {
            $this->respond(['success' => false, 'message' => $error], 422);
        }
        $success = $this->JobOrders->updateProductionTimeframe($id, $endDate);
        $this->respond(['success' => $success, 'message' => $success ? 'Production timeframe updated successfully.' : 'Failed to update production timeframe.']);
    }

    public function complete(): void
    {
        $this->requirePermission('production.complete');
        $this->requirePost();
        $id = trim($_POST['job_order_id'] ?? '');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->respond(['success' => false, 'message' => 'Job Order not found.'], 404);
        }
        if ($job->status !== 'Processing') {
            $this->respond(['success' => false, 'message' => 'Only Job Orders in production can be marked production finished.'], 422);
        }
        $user = Auth::user() ?? [];
        $success = $this->JobOrders->complete($id, isset($user['id']) ? (int) $user['id'] : null);
        $this->respond([
            'success' => $success,
            'message' => $success
                ? 'Production marked finished. The Job Order is ready for release.'
                : 'Production could not be marked finished. Confirm the approval, assignment, production dates, and current Order status.'
        ], $success ? 200 : 409);
    }
}
