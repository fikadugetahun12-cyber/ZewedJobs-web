<?php
/**
 * Job Search Routes
 */

function handleJobRoutes($method, $action, $id, $data, $auth, $response) {
    // Some endpoints don't require authentication
    $publicEndpoints = ['search', 'list', 'details', 'categories'];
    
    if (!in_array($action, $publicEndpoints) && !$auth->isAuthenticated()) {
        $response->error('Authentication required', 401);
        return;
    }
    
    $userId = $auth->getUserId();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'search':
                    searchJobs($data, $response);
                    break;
                    
                case 'list':
                    listJobs($data, $response);
                    break;
                    
                case 'details':
                    getJobDetails($id, $response);
                    break;
                    
                case 'categories':
                    getJobCategories($response);
                    break;
                    
                case 'recommended':
                    getRecommendedJobs($userId, $response);
                    break;
                    
                case 'applications':
                    getJobApplications($userId, $response);
                    break;
                    
                case 'saved':
                    getSavedJobs($userId, $response);
                    break;
                    
                default:
                    $response->error('Invalid job action', 400);
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'apply':
                    applyForJob($userId, $data, $response);
                    break;
                    
                case 'save':
                    saveJob($userId, $data, $response);
                    break;
                    
                case 'track':
                    trackJobApplication($userId, $data, $response);
                    break;
                    
                default:
                    $response->error('Invalid job action', 400);
            }
            break;
            
        case 'PUT':
            switch ($action) {
                case 'application':
                    updateApplication($userId, $id, $data, $response);
                    break;
                    
                default:
                    $response->error('Invalid job action', 400);
            }
            break;
            
        case 'DELETE':
            switch ($action) {
                case 'saved':
                    removeSavedJob($userId, $id, $response);
                    break;
                    
                default:
                    $response->error('Invalid job action', 400);
            }
            break;
            
        default:
            $response->error('Method not allowed', 405);
    }
}

function searchJobs($data, $response) {
    global $db;
    
    $query = $_GET['q'] ?? '';
    $location = $_GET['location'] ?? '';
    $category = $_GET['category'] ?? '';
    $type = $_GET['type'] ?? '';
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    // Build where clause
    $where = 'status = "active"';
    $params = [];
    
    if ($query) {
        $where .= ' AND (title LIKE ? OR company LIKE ? OR description LIKE ?)';
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($location) {
        $where .= ' AND location LIKE ?';
        $params[] = "%$location%";
    }
    
    if ($category) {
        $where .= ' AND category = ?';
        $params[] = $category;
    }
    
    if ($type) {
        $where .= ' AND type = ?';
        $params[] = $type;
    }
    
    // Get jobs
    $jobs = $db->select('jobs', '*', $where, $params, "$limit OFFSET $offset", 'posted_date DESC');
    
    // Get total count
    $total = $db->query("SELECT COUNT(*) as total FROM jobs WHERE $where", $params);
    $total = $total[0]['total'] ?? 0;
    
    // Format response
    foreach ($jobs as &$job) {
        $job['posted_ago'] = timeAgo($job['posted_date']);
    }
    
    $response->success([
        'jobs' => $jobs,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function listJobs($data, $response) {
    global $db;
    
    $featured = $_GET['featured'] ?? false;
    $recent = $_GET['recent'] ?? false;
    $limit = $_GET['limit'] ?? 10;
    
    $where = 'status = "active"';
    $order = 'posted_date DESC';
    
    if ($featured) {
        $where .= ' AND featured = 1';
    }
    
    if ($recent) {
        // Last 7 days
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $where .= ' AND posted_date > ?';
        $params[] = $weekAgo;
    }
    
    $jobs = $db->select('jobs', '*', $where, $params ?? [], $limit, $order);
    
    // Format response
    foreach ($jobs as &$job) {
        $job['posted_ago'] = timeAgo($job['posted_date']);
    }
    
    $response->success(['jobs' => $jobs]);
}

function getJobDetails($jobId, $response) {
    global $db;
    
    $job = $db->select('jobs', '*', 'id = ? AND status = "active"', [$jobId], 1);
    
    if (empty($job)) {
        $response->error('Job not found', 404);
        return;
    }
    
    $job = $job[0];
    $job['posted_ago'] = timeAgo($job['posted_date']);
    
    // Get similar jobs
    $similarJobs = $db->select('jobs', '*', 
        'category = ? AND id != ? AND status = "active"', 
        [$job['category'], $jobId], 5, 'posted_date DESC');
    
    $response->success([
        'job' => $job,
        'similar_jobs' => $similarJobs
    ]);
}

function getJobCategories($response) {
    global $db;
    
    $categories = $db->query("
        SELECT category, COUNT(*) as job_count
        FROM jobs 
        WHERE status = 'active'
        GROUP BY category
        ORDER BY job_count DESC
    ");
    
    $response->success(['categories' => $categories]);
}

function getRecommendedJobs($userId, $response) {
    global $db;
    
    // Get user's skills and preferences
    $user = $db->select('users', 'skills, preferences', 'id = ?', [$userId], 1);
    
    if (empty($user)) {
        $response->error('User not found', 404);
        return;
    }
    
    $user = $user[0];
    $skills = json_decode($user['skills'] ?? '[]', true);
    $preferences = json_decode($user['preferences'] ?? '[]', true);
    
    // Simple recommendation logic
    // In production, use more sophisticated algorithms
    $recommended = [];
    
    if (!empty($skills)) {
        foreach ($skills as $skill) {
            $jobs = $db->query("
                SELECT * FROM jobs 
                WHERE (title LIKE ? OR description LIKE ?) 
                AND status = 'active'
                ORDER BY posted_date DESC 
                LIMIT 5
            ", ["%$skill%", "%$skill%"]);
            
            $recommended = array_merge($recommended, $jobs);
        }
    }
    
    // Remove duplicates
    $recommended = array_unique($recommended, SORT_REGULAR);
    
    $response->success(['jobs' => array_slice($recommended, 0, 10)]);
}

function applyForJob($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'job_id' => 'required|integer',
        'cover_letter' => 'sometimes|min:10|max:2000',
        'resume_id' => 'sometimes|integer'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Check if job exists
    $job = $db->select('jobs', 'id, title, company', 'id = ? AND status = "active"', [$data['job_id']], 1);
    
    if (empty($job)) {
        $response->error('Job not found', 404);
        return;
    }
    
    // Check if already applied
    $existing = $db->select('job_applications', 'id', 'user_id = ? AND job_id = ?', [$userId, $data['job_id']], 1);
    
    if (!empty($existing)) {
        $response->error('Already applied for this job', 409);
        return;
    }
    
    // Get user's default resume if not specified
    $resumeId = $data['resume_id'] ?? null;
    if (!$resumeId) {
        $resume = $db->select('user_files', 'id', 'user_id = ? AND filetype IN ("pdf", "doc", "docx")', [$userId], 1, 'created_at DESC');
        $resumeId = $resume[0]['id'] ?? null;
    }
    
    // Create application
    $application = $db->insert('job_applications', [
        'user_id' => $userId,
        'job_id' => $data['job_id'],
        'resume_id' => $resumeId,
        'cover_letter' => $data['cover_letter'] ?? '',
        'status' => 'applied',
        'applied_date' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Update application count
    $db->query("UPDATE jobs SET application_count = application_count + 1 WHERE id = ?", [$data['job_id']]);
    
    // Create notification
    $db->insert('notifications', [
        'user_id' => $userId,
        'type' => 'application',
        'title' => 'Application Submitted',
        'message' => "You applied for: " . $job[0]['title'] . " at " . $job[0]['company'],
        'data' => json_encode(['job_id' => $data['job_id']]),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'application_id' => $application['insert_id'],
        'message' => 'Application submitted successfully'
    ], 201);
}

function saveJob($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'job_id' => 'required|integer'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Check if job exists
    $job = $db->select('jobs', 'id', 'id = ?', [$data['job_id']], 1);
    
    if (empty($job)) {
        $response->error('Job not found', 404);
        return;
    }
    
    // Check if already saved
    $existing = $db->select('saved_jobs', 'id', 'user_id = ? AND job_id = ?', [$userId, $data['job_id']], 1);
    
    if (!empty($existing)) {
        $response->error('Job already saved', 409);
        return;
    }
    
    // Save job
    $saved = $db->insert('saved_jobs', [
        'user_id' => $userId,
        'job_id' => $data['job_id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'saved_id' => $saved['insert_id'],
        'message' => 'Job saved successfully'
    ], 201);
}

function getJobApplications($userId, $response) {
    global $db;
    
    $status = $_GET['status'] ?? '';
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    // Build query
    $where = 'ja.user_id = ?';
    $params = [$userId];
    
    if ($status) {
        $where .= ' AND ja.status = ?';
        $params[] = $status;
    }
    
    $applications = $db->query("
        SELECT ja.*, j.title, j.company, j.location, j.type, j.posted_date
        FROM job_applications ja
        JOIN jobs j ON ja.job_id = j.id
        WHERE $where
        ORDER BY ja.applied_date DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    // Get total count
    $total = $db->query("
        SELECT COUNT(*) as total 
        FROM job_applications ja
        WHERE $where
    ", $params);
    $total = $total[0]['total'] ?? 0;
    
    // Format dates
    foreach ($applications as &$app) {
        $app['applied_ago'] = timeAgo($app['applied_date']);
    }
    
    $response->success([
        'applications' => $applications,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getSavedJobs($userId, $response) {
    global $db;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    $savedJobs = $db->query("
        SELECT j.*, sj.created_at as saved_date
        FROM saved_jobs sj
        JOIN jobs j ON sj.job_id = j.id
        WHERE sj.user_id = ? AND j.status = 'active'
        ORDER BY sj.created_at DESC
        LIMIT ? OFFSET ?
    ", [$userId, $limit, $offset]);
    
    // Get total count
    $total = $db->query("
        SELECT COUNT(*) as total 
        FROM saved_jobs sj
        JOIN jobs j ON sj.job_id = j.id
        WHERE sj.user_id = ? AND j.status = 'active'
    ", [$userId]);
    $total = $total[0]['total'] ?? 0;
    
    foreach ($savedJobs as &$job) {
        $job['posted_ago'] = timeAgo($job['posted_date']);
        $job['saved_ago'] = timeAgo($job['saved_date']);
    }
    
    $response->success([
        'jobs' => $savedJobs,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function trackJobApplication($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'company' => 'required|min:2|max:100',
        'position' => 'required|min:2|max:100',
        'status' => 'required|in:applied,interviewing,offer,rejected'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    $tracking = $db->insert('job_tracking', [
        'user_id' => $userId,
        'company' => $data['company'],
        'position' => $data['position'],
        'status' => $data['status'],
        'application_url' => $data['application_url'] ?? '',
        'notes' => $data['notes'] ?? '',
        'applied_date' => $data['applied_date'] ?? date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'tracking_id' => $tracking['insert_id'],
        'message' => 'Job application tracked successfully'
    ], 201);
}

function updateApplication($userId, $appId, $data, $response) {
    global $db;
    
    // Verify ownership
    $application = $db->select('job_applications', 'id', 'id = ? AND user_id = ?', [$appId, $userId], 1);
    
    if (empty($application)) {
        $response->error('Application not found', 404);
        return;
    }
    
    $validation = new Validation();
    $validation->validate($data, [
        'status' => 'sometimes|in:applied,interviewing,offer,rejected',
        'notes' => 'sometimes|max:500'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    $updateData = [
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (isset($data['status'])) {
        $updateData['status'] = $data['status'];
    }
    
    if (isset($data['notes'])) {
        $updateData['notes'] = $data['notes'];
    }
    
    $db->update('job_applications', $updateData, 'id = ?', [$appId]);
    
    $response->success(['message' => 'Application updated']);
}

function removeSavedJob($userId, $savedId, $response) {
    global $db;
    
    // Verify ownership
    $saved = $db->select('saved_jobs', 'id', 'id = ? AND user_id = ?', [$savedId, $userId], 1);
    
    if (empty($saved)) {
        $response->error('Saved job not found', 404);
        return;
    }
    
    $db->delete('saved_jobs', 'id = ?', [$savedId]);
    
    $response->success(['message' => 'Job removed from saved list']);
}

// Helper function
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
