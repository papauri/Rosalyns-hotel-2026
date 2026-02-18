<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';
// permissions.php already loaded by admin-init.php

// Require access to user management module
if (!hasPermission($user['id'], 'user_management')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$site_name = getSetting('site_name');
$success_msg = '';
$error_msg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_msg = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // ---- ADD NEW USER ----
        if ($action === 'add_user') {
            if (!hasPermission($user['id'], 'user_create')) {
                $error_msg = 'You do not have permission to create users.';
            } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'receptionist';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
                $error_msg = 'All fields are required.';
            } elseif (strlen($password) < 8) {
                $error_msg = 'Password must be at least 8 characters.';
            } elseif (!in_array($role, ['admin', 'manager', 'receptionist'])) {
                $error_msg = 'Invalid role selected.';
            } else {
                // Check for duplicate username/email
                $check = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = 'Username or email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hash, $full_name, $role]);
                    $success_msg = "User '{$full_name}' created successfully.";
                }
            }
            }
        }
        
        // ---- UPDATE USER ----
        elseif ($action === 'update_user') {
            if (!hasPermission($user['id'], 'user_edit')) {
                $error_msg = 'You do not have permission to edit users.';
            } else {
            $uid = (int)($_POST['user_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'receptionist';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $new_password = $_POST['new_password'] ?? '';
            
            if ($uid <= 0 || empty($full_name) || empty($email)) {
                $error_msg = 'Full name and email are required.';
            } elseif (!in_array($role, ['admin', 'manager', 'receptionist'])) {
                $error_msg = 'Invalid role selected.';
            } else {
                // Check email uniqueness (excluding current user)
                $check = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ? AND id != ?");
                $check->execute([$email, $uid]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = 'Email already in use by another user.';
                } else {
                    if (!empty($new_password)) {
                        if (strlen($new_password) < 8) {
                            $error_msg = 'Password must be at least 8 characters.';
                        } else {
                            $hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, email = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?");
                            $stmt->execute([$full_name, $email, $role, $is_active, $hash, $uid]);
                            $success_msg = "User updated successfully (including password).";
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$full_name, $email, $role, $is_active, $uid]);
                        $success_msg = "User updated successfully.";
                    }
                }
            }
            }
        }
        
        // ---- SAVE PERMISSIONS ----
        elseif ($action === 'save_permissions') {
            if (!hasPermission($user['id'], 'user_permissions')) {
                $error_msg = 'You do not have permission to modify user permissions.';
            } else {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid <= 0) {
                $error_msg = 'Invalid user.';
            } else {
                // Ensure not editing admin's permissions
                $check_role = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $check_role->execute([$uid]);
                $target_role = $check_role->fetchColumn();
                
                if ($target_role === 'admin') {
                    $error_msg = 'Cannot modify admin permissions.';
                } else {
                    $all_perms = getAllPermissions();
                    $granted = $_POST['permissions'] ?? [];
                    $perms_to_set = [];
                    
                    foreach ($all_perms as $key => $info) {
                        if ($key === 'user_management') continue; // Admin-only
                        $perms_to_set[$key] = in_array($key, $granted);
                    }
                    
                    if (setUserPermissions($uid, $perms_to_set, $user['id'])) {
                        $success_msg = "Permissions updated successfully.";
                    } else {
                        $error_msg = "Failed to update permissions.";
                    }
                }
            }
            }
        }
        
        // ---- DELETE USER ----
        elseif ($action === 'delete_user') {
            if (!hasPermission($user['id'], 'user_delete')) {
                $error_msg = 'You do not have permission to delete users.';
            } else {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid <= 0) {
                $error_msg = 'Invalid user.';
            } elseif ($uid === (int)$user['id']) {
                $error_msg = 'You cannot delete your own account.';
            } else {
                // Don't allow deleting the last admin
                $admin_count = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                $check_role = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $check_role->execute([$uid]);
                $target_role = $check_role->fetchColumn();
                
                if ($target_role === 'admin' && $admin_count <= 1) {
                    $error_msg = 'Cannot delete the last admin user.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                    $stmt->execute([$uid]);
                    $success_msg = "User deleted successfully.";
                }
            }
            }
        }
    }
}

// Fetch all users
$users_stmt = $pdo->query("SELECT * FROM admin_users ORDER BY role ASC, full_name ASC");
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// If editing a specific user's permissions
$editing_user_id = isset($_GET['permissions']) ? (int)$_GET['permissions'] : 0;
$editing_user = null;
$editing_permissions = [];
if ($editing_user_id > 0) {
    foreach ($all_users as $u) {
        if ($u['id'] == $editing_user_id) {
            $editing_user = $u;
            break;
        }
    }
    if ($editing_user && $editing_user['role'] !== 'admin') {
        $editing_permissions = getUserPermissions($editing_user_id);
    }
}

$all_permissions = getAllPermissions();
$permission_categories = [];
foreach ($all_permissions as $key => $info) {
    if ($key === 'user_management') continue;
    $permission_categories[$info['category']][$key] = $info;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/base/critical.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css"></head>
<body>

<?php require_once 'includes/admin-header.php'; ?>

<main class="admin-content">
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
    <div class="access-denied">
        <i class="fas fa-exclamation-triangle"></i>
        You do not have permission to access that page.
    </div>
    <?php endif; ?>
    
    <?php if ($success_msg): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
    </div>
    <?php endif; ?>
    
    <!-- USERS LIST -->
    <div class="page-header">
        <h2><i class="fas fa-users-cog"></i> User Management</h2>
        <?php if (hasPermission($user['id'], 'user_create')): ?>
        <button class="btn-add" onclick="Modal.open('addUserModal')">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
        <?php endif; ?>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_users as $u): ?>
                <tr>
                    <td data-label="User">
                        <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                    </td>
                    <td data-label="Username"><?php echo htmlspecialchars($u['username']); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td data-label="Role">
                        <span class="role-badge <?php echo $u['role']; ?>">
                            <?php echo ucfirst($u['role']); ?>
                        </span>
                    </td>
                    <td data-label="Status">
                        <span class="status-badge <?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td data-label="Last Login">
                        <span class="last-login">
                            <?php echo $u['last_login'] ? date('M j, Y g:ia', strtotime($u['last_login'])) : 'Never'; ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <div class="actions-cell">
                            <?php if (hasPermission($user['id'], 'user_edit')): ?>
                            <button type="button" class="btn-sm btn-edit js-edit-user" data-user="<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php endif; ?>
                            <?php if ($u['role'] !== 'admin' && hasPermission($user['id'], 'user_permissions')): ?>
                            <a href="?permissions=<?php echo $u['id']; ?>" class="btn-sm btn-permissions">
                                <i class="fas fa-shield-alt"></i> Permissions
                            </a>
                            <?php endif; ?>
                            <?php if ($u['id'] != $user['id'] && hasPermission($user['id'], 'user_delete')): ?>
                            <button type="button" class="btn-sm btn-delete" onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- PERMISSIONS EDITOR -->
    <?php if ($editing_user && $editing_user['role'] !== 'admin' && hasPermission($user['id'], 'user_permissions')): ?>
    <form method="POST" id="permissionsForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="save_permissions">
        <input type="hidden" name="user_id" value="<?php echo $editing_user['id']; ?>">
        
        <div class="permissions-panel">
            <div class="permissions-header">
                <h3><i class="fas fa-shield-alt"></i> Edit Permissions</h3>
                <div class="perm-user-info">
                    <span class="perm-user-name"><?php echo htmlspecialchars($editing_user['full_name']); ?></span>
                    <span class="role-badge <?php echo $editing_user['role']; ?>"><?php echo ucfirst($editing_user['role']); ?></span>
                </div>
            </div>
            <div class="permissions-body">
                
                <div class="quick-actions">
                    <button type="button" class="btn-select-all" onclick="selectAllPerms(true)">
                        <i class="fas fa-check-double"></i> Select All
                    </button>
                    <button type="button" class="btn-select-none" onclick="selectAllPerms(false)">
                        <i class="fas fa-times"></i> Deselect All
                    </button>
                    <button type="button" class="btn-select-defaults" onclick="selectDefaults()">
                        <i class="fas fa-undo"></i> Reset to Role Defaults
                    </button>
                </div>
                
                <?php foreach ($permission_categories as $cat_name => $cat_perms): ?>
                <div class="perm-category">
                    <h4 class="perm-category-title"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($cat_name); ?></h4>
                    <div class="perm-grid">
                        <?php foreach ($cat_perms as $perm_key => $perm_info): ?>
                        <?php 
                            $is_checked = isset($editing_permissions[$perm_key]) && $editing_permissions[$perm_key];
                        ?>
                        <label class="perm-item <?php echo $is_checked ? 'checked' : ''; ?>" id="perm-label-<?php echo $perm_key; ?>">
                            <div class="perm-icon">
                                <i class="fas <?php echo htmlspecialchars($perm_info['icon']); ?>"></i>
                            </div>
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="<?php echo htmlspecialchars($perm_key); ?>"
                                   <?php echo $is_checked ? 'checked' : ''; ?>
                                   onchange="togglePermItem(this)"
                                   data-default="<?php echo in_array($perm_key, getDefaultPermissionsForRole($editing_user['role'])) ? '1' : '0'; ?>">
                            <div class="perm-info">
                                <div class="perm-label"><?php echo htmlspecialchars($perm_info['label']); ?></div>
                                <div class="perm-desc"><?php echo htmlspecialchars($perm_info['description']); ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="perm-actions">
                    <a href="user-management.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                    <button type="submit" class="btn-save-perms">
                        <i class="fas fa-save"></i> Save Permissions
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php elseif ($editing_user && $editing_user['role'] === 'admin'): ?>
    <div class="permissions-panel" style="margin-top: 24px;">
        <div class="permissions-body" style="text-align: center; padding: 40px;">
            <i class="fas fa-crown" style="font-size: 48px; color: var(--gold, #c9a44a); margin-bottom: 16px;"></i>
            <h3 style="margin: 0 0 8px;">Admin Role</h3>
            <p style="color: #888; margin: 0;">Admin users have full access to all features. Their permissions cannot be restricted.</p>
            <a href="user-management.php" class="btn-cancel" style="margin-top: 20px;">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>
    <?php endif; ?>
    
</main>

<!-- ADD USER MODAL -->
<?php if (hasPermission($user['id'], 'user_create')): ?>
<div class="modal-overlay" id="addUserModal-overlay" data-modal-overlay></div>
<div class="modal-wrapper modal-md" id="addUserModal" data-modal>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <label for="add-fullname">Full Name</label>
                    <input type="text" id="add-fullname" name="full_name" required placeholder="e.g. Jane Banda">
                </div>
                <div class="form-row">
                    <label for="add-username">Username</label>
                    <input type="text" id="add-username" name="username" required placeholder="e.g. jane.b" pattern="[a-zA-Z0-9._-]+" title="Letters, numbers, dots, dashes, underscores only">
                </div>
                <div class="form-row">
                    <label for="add-email">Email</label>
                    <input type="email" id="add-email" name="email" required placeholder="e.g. jane@example.com">
                </div>
                <div class="form-row">
                    <label for="add-role">Role</label>
                    <select id="add-role" name="role">
                        <option value="receptionist">Receptionist</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <div class="hint">Role determines default permissions. You can customize later.</div>
                </div>
                <div class="form-row">
                    <label for="add-password">Password</label>
                    <input type="password" id="add-password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="btn-save-perms">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- EDIT USER MODAL -->
<?php if (hasPermission($user['id'], 'user_edit')): ?>
<div class="modal-overlay" id="editUserModal-overlay" data-modal-overlay></div>
<div class="modal-wrapper modal-md" id="editUserModal" data-modal>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit-user-id">
            
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <label for="edit-fullname">Full Name</label>
                    <input type="text" id="edit-fullname" name="full_name" required>
                </div>
                <div class="form-row">
                    <label for="edit-email">Email</label>
                    <input type="email" id="edit-email" name="email" required>
                </div>
                <div class="form-row">
                    <label for="edit-role">Role</label>
                    <select id="edit-role" name="role">
                        <option value="receptionist">Receptionist</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>
                        <input type="checkbox" name="is_active" id="edit-active" value="1" style="width:auto; margin-right: 6px;">
                        Active Account
                    </label>
                </div>
                <div class="form-row">
                    <label for="edit-password">New Password <span style="font-weight:400; color:#888;">(leave blank to keep current)</span></label>
                    <input type="password" id="edit-password" name="new_password" minlength="8" placeholder="Leave blank to keep unchanged">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="btn-save-perms">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- DELETE FORM (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete-user-id">
</form>

<script src="js/admin-components.js"></script>
<script>
function openEditModal(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-fullname').value = user.full_name;
    document.getElementById('edit-email').value = user.email;
    document.getElementById('edit-role').value = user.role;
    document.getElementById('edit-active').checked = user.is_active == 1;
    document.getElementById('edit-password').value = '';
    Modal.open('editUserModal');
}

// Bind edit buttons (data-user JSON)
document.querySelectorAll('.js-edit-user').forEach(btn => {
    btn.addEventListener('click', function() {
        const raw = this.getAttribute('data-user');
        if (!raw) return;
        try {
            const user = JSON.parse(raw);
            openEditModal(user);
        } catch (e) {
            console.error('Failed to parse user data for edit modal.', e);
        }
    });
});

function confirmDelete(userId, userName) {
    if (confirm('Are you sure you want to delete user "' + userName + '"?\n\nThis action cannot be undone and will remove all their permissions.')) {
        document.getElementById('delete-user-id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

function togglePermItem(checkbox) {
    const label = checkbox.closest('.perm-item');
    if (checkbox.checked) {
        label.classList.add('checked');
    } else {
        label.classList.remove('checked');
    }
}

function selectAllPerms(checked) {
    document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
        cb.checked = checked;
        togglePermItem(cb);
    });
}

function selectDefaults() {
    document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
        cb.checked = cb.dataset.default === '1';
        togglePermItem(cb);
    });
}

// Modal system is handled by admin-components.js
</script>

<?php require_once 'includes/admin-footer.php'; ?>
