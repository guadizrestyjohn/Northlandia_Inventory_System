<?php

namespace Api\JobOrders;

use Core\BaseController;
use Core\Auth;
use Models\JobOrders;
use Models\Orders;
use Models\Users;

class JobOrdersController extends BaseController
{
    private $JobOrders;
    private $Orders;
    private $Users;

    public function __construct()
    {
        $this->JobOrders = new JobOrders();
        $this->Orders = new Orders();
        $this->Users = new Users();
    }

    public function all(): void
    {
        $this->requirePermission('job_orders.view');
        $this->requireGet();
        $access = $this->productionAccessForCurrentUser();
        $this->json(['data' => $this->JobOrders->find($access['teams'] ?? null)]);
    }

    public function eligibleOrders(): void
    {
        $this->requirePermission('job_orders.create');
        $this->requireGet();
        $this->json(['data' => $this->JobOrders->eligiblePendingOrders()]);
    }

    public function add(): void
    {
        $this->requirePermission('job_orders.create');
        $this->requirePost();
        $orderId = $this->postString('order_id');
        if (!ctype_digit($orderId) || !$this->Orders->findByID($orderId)) {
            $this->json(['status' => 'error', 'message' => 'Please select a valid order.']);
        }
        $result = $this->JobOrders->create($orderId);
        $this->respondResult($result);
    }

    public function approval(): void
    {
        $this->requirePermission('job_orders.approval');
        $this->requirePost();
        $id = $this->postString('job_order_id');
        $approval = $this->postString('customer_approval');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        if (!in_array($approval, ['Pending', 'Approved', 'Rejected'], true)) {
            $this->json(['status' => 'error', 'message' => 'Invalid customer approval.']);
        }
        if ($job->status !== 'Pending' || $job->customer_approval !== 'Pending') {
            $this->json(['status' => 'error', 'message' => 'Customer approval can no longer be changed after a decision has been recorded.']);
        }
        $success = $this->JobOrders->updateCustomerApproval($id, $approval);
        $this->json(['status' => $success ? 'success' : 'error',
            'message' => $success ? 'Customer approval updated successfully.' : 'Customer approval could not be updated. The Job Order may have progressed.']);
    }

    public function endorse(): void
    {
        $this->requirePermission('job_orders.endorse');
        $this->requirePost();
        $id = $this->postString('job_order_id');
        $assignment = $this->postString('production_assignment');
        $assignedTeam = $this->postString('assigned_team');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        if ($assignment === '') {
            $this->json(['status' => 'error', 'message' => 'Enter the responsible worker, trade, or contractor.']);
        }
        if (mb_strlen($assignment) > 100) {
            $this->json(['status' => 'error', 'message' => 'The production assignment must be 100 characters or fewer.']);
        }
        if ($assignedTeam === '' || mb_strlen($assignedTeam) > 100
            || !$this->Users->productionTeamExists($assignedTeam)) {
            $this->json(['status' => 'error', 'message' => 'Select an active Production Team. Configure a Production Staff team in User Management first.']);
        }
        if ($job->customer_approval !== 'Approved') {
            $this->json(['status' => 'error', 'message' => 'Customer approval is required before production.']);
        }
        $success = $this->JobOrders->endorseToProduction($id, $assignment, $assignedTeam);
        $this->json(['status' => $success ? 'success' : 'error',
            'message' => $success ? 'Job Order endorsed to production successfully.' : 'Job Order could not be endorsed. Check its current status and approval.']);
    }

    public function timeframe(): void
    {
        $this->requirePermission('production.timeframe');
        $this->requirePost();
        $id = $this->postString('job_order_id');
        $endDate = $this->postString('production_end_date');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        $this->requireAssignmentAccess($job);
        $error = JobOrders::productionEndDateError($endDate, $job->production_start_date ? (string) $job->production_start_date : null);
        if ($error !== null) {
            $this->json(['status' => 'error', 'message' => $error]);
        }
        $success = $this->JobOrders->updateProductionTimeframe($id, $endDate);
        $this->json(['status' => $success ? 'success' : 'error',
            'message' => $success ? 'Production timeframe updated successfully.' : 'Failed to update production timeframe.']);
    }

    public function complete(): void
    {
        $this->requirePermission('production.complete');
        $this->requirePost();
        $id = $this->postString('job_order_id');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        $this->requireAssignmentAccess($job);
        if ($job->status !== 'Processing') {
            $this->json(['status' => 'error', 'message' => 'Only Job Orders in production can be marked production finished.']);
        }
        $user = Auth::user() ?? [];
        $success = $this->JobOrders->complete($id, isset($user['id']) ? (int) $user['id'] : null);
        $this->json([
            'status' => $success ? 'success' : 'error',
            'message' => $success
                ? 'Production marked finished. The Job Order is ready for release.'
                : 'Production could not be marked finished. Confirm the approval, assignment, production dates, and current Order status.'
        ]);
    }

    public function reportOutput(): void
    {
        $this->requirePermission('job_orders.view');
        $this->requirePost();
        $user = Auth::user() ?? [];
        $role = $user['role'] ?? '';
        if (!in_array($role, ['Administrator', 'Production Staff'], true)) {
            $this->json(['status' => 'error', 'message' => 'You are not authorized to report production output.']);
        }

        $id = $this->postString('job_order_id');
        $job = ctype_digit($id) ? $this->JobOrders->findByID($id) : null;
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        if ($role === 'Production Staff') {
            $this->requireAssignmentAccess($job);
            $currentUser = $this->Users->findByID((int) ($user['id'] ?? 0));
            $team = trim((string) ($currentUser->production_team ?? ''));
        } else {
            $team = trim((string) ($job->assigned_team ?? ''));
        }
        $result = $this->JobOrders->reportProductionOutput(
            (int) $id,
            $this->postString('product_name'),
            $this->postString('item_description'),
            $this->postString('width_cm'),
            $this->postString('height_cm'),
            $this->postString('reported_quantity'),
            $this->postString('submission_key'),
            (int) ($user['id'] ?? 0),
            $team
        );
        $this->respondResult($result);
    }

    public function outputs(string $id): void
    {
        $this->requirePermission('job_orders.view');
        $this->requireGet();
        $user = Auth::user() ?? [];
        if (!in_array($user['role'] ?? '', ['Administrator', 'Manager', 'Production Staff'], true)) {
            $this->json(['status' => 'error', 'message' => 'You are not authorized to view production output.']);
        }
        if (!ctype_digit($id) || (int) $id < 1) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        $job = $this->JobOrders->findByID($id);
        if (!$job) {
            $this->json(['status' => 'error', 'message' => 'Job Order not found.']);
        }
        $this->requireAssignmentAccess($job);
        $this->json(['data' => $this->JobOrders->findProductionOutputs((int) $id)]);
    }

    private function respondResult(array $result): void
    {
        $successful = !empty($result['success']);
        unset($result['success'], $result['status']);
        if (empty($result['message'])) {
            $result['message'] = $successful
                ? 'Operation completed successfully.'
                : 'Unable to complete the operation.';
        }
        $result['status'] = $successful ? 'success' : 'error';
        $this->json($result);
    }

    private function requireLogin(): void
    {
        if (!Auth::check()) {
            $this->json(['status' => 'error', 'message' => 'You must be logged in.']);
        }
    }

    private function requirePermission(string $permission): void
    {
        $this->requireLogin();
        if (!Auth::can($permission)) {
            $this->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.',
            ]);
        }
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'POST is required.']);
        }
    }

    private function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(['status' => 'error', 'message' => 'GET is required.']);
        }
    }

    private function postString(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        return $trim ? trim($value) : $value;
    }

    private function productionAccessForCurrentUser(): ?array
    {
        $user = Auth::user() ?? [];
        if (($user['role'] ?? '') !== 'Production Staff') {
            return null;
        }

        $currentUser = $this->Users->findByID((int) ($user['id'] ?? 0));

        return [
            'teams' => array_values(array_unique(array_filter([
                trim((string) ($currentUser->production_team ?? '')),
            ], static fn($team) => $team !== ''))),
        ];
    }

    private function requireAssignmentAccess(object $job): void
    {
        $access = $this->productionAccessForCurrentUser();
        if ($access === null) {
            return;
        }

        $assignedTeam = trim((string) ($job->assigned_team ?? ''));
        foreach ($access['teams'] as $team) {
            if ($assignedTeam !== '' && strcasecmp($assignedTeam, $team) === 0) {
                return;
            }
        }

        $this->json([
            'status' => 'error',
            'message' => 'This Job Order is not assigned to your production account.',
        ]);
    }
}
