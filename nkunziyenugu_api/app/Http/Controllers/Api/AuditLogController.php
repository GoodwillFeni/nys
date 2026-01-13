<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    /**
     * Get audit logs with filtering
     */
    public function getLogs(Request $request)
    {
        $authUser = $request->user();
        $accountId = $request->header('X-Account-ID');

        // Build query
        $query = AuditLog::with(['user', 'account'])
            ->orderBy('created_at', 'desc');

        // Filter by account if not super admin
        if (!$authUser->is_super_admin) {
            if (!$accountId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Active account not selected'
                ], 400);
            }
            $query->where('account_id', $accountId);
        } elseif ($request->has('account_id')) {
            // Super admin can filter by specific account
            $query->where('account_id', $request->account_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function getStatistics(Request $request)
    {
        $authUser = $request->user();
        $accountId = $request->header('X-Account-ID');

        $query = AuditLog::query();

        // Filter by account if not super admin
        if (!$authUser->is_super_admin && $accountId) {
            $query->where('account_id', $accountId);
        } elseif ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_logs' => $query->count(),
            'by_action' => $query->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'by_model' => $query->select('model_type', DB::raw('count(*) as count'))
                ->groupBy('model_type')
                ->pluck('count', 'model_type')
                ->toArray(),
            'recent_activity' => $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'action', 'model_type', 'description', 'created_at'])
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}
