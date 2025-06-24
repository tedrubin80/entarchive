<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_location':
            handleAddLocation();
            break;
        case 'update_location':
            handleUpdateLocation();
            break;
        case 'delete_location':
            handleDeleteLocation();
            break;
        case 'move_items':
            handleMoveItems();
            break;
    }
}

function handleAddLocation() {
    global $pdo;
    
    try {
        $sql = "INSERT INTO storage_locations (
            name, description, location_type, parent_location_id, 
            address, access_notes, capacity_limit, climate_controlled, security_level
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?: null,
            $_POST['location_type'],
            $_POST['parent_location_id'] ?: null,
            $_POST['address'] ?: null,
            $_POST['access_notes'] ?: null,
            $_POST['capacity_limit'] ?: null,
            isset($_POST['climate_controlled']) ? 1 : 0,
            $_POST['security_level']
        ]);
        
        $success = "Location added successfully!";
    } catch (Exception $e) {
        $error = "Error adding location: " . $e->getMessage();
    }
}

function handleUpdateLocation() {
    global $pdo;
    
    try {
        $sql = "UPDATE storage_locations SET 
            name = ?, description = ?, location_type = ?, parent_location_id = ?,
            address = ?, access_notes = ?, capacity_limit = ?, 
            climate_controlled = ?, security_level = ?
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?: null,
            $_POST['location_type'],
            $_POST['parent_location_id'] ?: null,
            $_POST['address'] ?: null,
            $_POST['access_notes'] ?: null,
            $_POST['capacity_limit'] ?: null,
            isset($_POST['climate_controlled']) ? 1 : 0,
            $_POST['security_level'],
            $_POST['location_id']
        ]);
        
        $success = "Location updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating location: " . $e->getMessage();
    }
}

function handleDeleteLocation() {
    global $pdo;
    
    try {
        // Check if location has items
        $checkSql = "SELECT COUNT(*) as count FROM collection WHERE primary_location_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$_POST['location_id']]);
        $itemCount = $checkStmt->fetch()['count'];
        
        if ($itemCount > 0) {
            throw new Exception("Cannot delete location that contains {$itemCount} items. Move items first.");
        }
        
        $sql = "DELETE FROM storage_locations WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['location_id']]);
        
        $success = "Location deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting location: " . $e->getMessage();
    }
}

function handleMoveItems() {
    global $pdo;
    
    try {
        $fromLocationId = $_POST['from_location_id'];
        $toLocationId = $_POST['to_location_id'];
        
        $sql = "UPDATE collection SET primary_location_id = ? WHERE primary_location_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$toLocationId, $fromLocationId]);
        
        $affectedRows = $stmt->rowCount();
        $success = "Moved {$affectedRows} items successfully!";
    } catch (Exception $e) {
        $error = "Error moving items: " . $e->getMessage();
    }
}

// Get location hierarchy
function getLocationHierarchy($pdo) {
    $sql = "SELECT 
        l.*,
        p.name as parent_name,
        (SELECT COUNT(*) FROM collection c WHERE c.primary_location_id = l.id) as item_count,
        (SELECT COUNT(*) FROM storage_locations child WHERE child.parent_location_id = l.id) as child_count
    FROM storage_locations l
    LEFT JOIN storage_locations p ON l.parent_location_id = p.id
    ORDER BY COALESCE(l.parent_location_id, l.id), l.name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

$locations = getLocationHierarchy($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Management - Media Collection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .location-controls {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .locations-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .locations-tree {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .location-stats {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .tree-item {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .tree-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        .tree-item.parent {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        
        .tree-item.child {
            margin-left: 2rem;
            border-left: 4px solid #28a745;
        }
        
        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .location-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .location-type {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-room { background: #e3f2fd; color: #1565c0; }
        .type-shelf { background: #f3e5f5; color: #7b1fa2; }
        .type-box { background: #fff3e0; color: #ef6c00; }
        .type-cabinet { background: #e8f5e8; color: #2e7d32; }
        .type-storage_unit { background: #fce4ec; color: #c2185b; }
        .type-offsite { background: #f1f8e9; color: #558b2f; }
        
        .location-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .location-stats-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .item-count {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .location-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .capacity-bar {
            width: 100%;
            height: 8px;
            background: #e1e5e9;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            transition: width 0.3s ease;
        }
        
        .security-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: 0.5rem;
        }
        
        .security-none { color: #dc3545; }
        .security-basic { color: #ffc107; }
        .security-high { color: #28a745; }
        .security-vault { color: #007bff; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .locations-grid {
                grid-template-columns: 1fr;
            }
            
            .tree-item.child {
                margin-left: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📍 Storage Location Management</h1>
        <p>Organize and track where your collection items are stored</p>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($locations); ?></div>
                <div class="stat-label">Total Locations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($locations, 'item_count')); ?></div>
                <div class="stat-label">Items Stored</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($locations, function($l) { return $l['climate_controlled']; })); ?></div>
                <div class="stat-label">Climate Controlled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($locations, function($l) { return $l['security_level'] === 'high' || $l['security_level'] === 'vault'; })); ?></div>
                <div class="stat-label">High Security</div>
            </div>
        </div>

        <div class="location-controls">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>Storage Locations</h2>
                <div style="display: flex; gap: 1rem;">
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Location</button>
                    <button class="btn btn-secondary" onclick="openMoveModal()">📦 Move Items</button>
                    <button class="btn btn-warning" onclick="generateLocationReport()">📊 Report</button>
                </div>
            </div>
        </div>

        <div class="locations-grid">
            <div class="locations-tree">
                <h3 style="margin-bottom: 1.5rem;">Location Hierarchy</h3>
                
                <?php
                $parentLocations = array_filter($locations, function($l) { return $l['parent_location_id'] === null; });
                foreach ($parentLocations as $parent):
                    $childLocations = array_filter($locations, function($l) use ($parent) { return $l['parent_location_id'] == $parent['id']; });
                    $capacityPercent = $parent['capacity_limit'] ? min(100, ($parent['item_count'] / $parent['capacity_limit']) * 100) : 0;
                ?>
                    <div class="tree-item parent">
                        <div class="location-header">
                            <div>
                                <span class="location-name"><?php echo htmlspecialchars($parent['name']); ?></span>
                                <span class="location-type type-<?php echo $parent['location_type']; ?>">
                                    <?php echo str_replace('_', ' ', $parent['location_type']); ?>
                                </span>
                                <?php if ($parent['climate_controlled']): ?>
                                    <span style="color: #007bff;">❄️</span>
                                <?php endif; ?>
                                <span class="security-indicator security-<?php echo $parent['security_level']; ?>">
                                    <?php
                                    $securityIcons = ['none' => '🔓', 'basic' => '🔒', 'high' => '🔐', 'vault' => '🏛️'];
                                    echo $securityIcons[$parent['security_level']];
                                    ?>
                                </span>
                            </div>
                            <span class="item-count"><?php echo $parent['item_count']; ?> items</span>
                        </div>
                        
                        <?php if ($parent['description']): ?>
                            <div class="location-meta"><?php echo htmlspecialchars($parent['description']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($parent['capacity_limit']): ?>
                            <div class="capacity-bar">
                                <div class="capacity-fill" style="width: <?php echo $capacityPercent; ?>%"></div>
                            </div>
                            <div class="location-meta">
                                Capacity: <?php echo $parent['item_count']; ?> / <?php echo $parent['capacity_limit']; ?> 
                                (<?php echo round($capacityPercent, 1); ?>%)
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($parent['address']): ?>
                            <div class="location-meta">📍 <?php echo htmlspecialchars($parent['address']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($parent['access_notes']): ?>
                            <div class="location-meta">ℹ️ <?php echo htmlspecialchars($parent['access_notes']); ?></div>
                        <?php endif; ?>
                        
                        <div class="location-actions">
                            <button class="btn btn-primary" onclick="editLocation(<?php echo $parent['id']; ?>)">✏️ Edit</button>
                            <button class="btn btn-warning" onclick="viewItems(<?php echo $parent['id']; ?>)">👁️ View Items</button>
                            <?php if ($parent['item_count'] == 0): ?>
                                <button class="btn btn-danger" onclick="deleteLocation(<?php echo $parent['id']; ?>)">🗑️ Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php foreach ($childLocations as $child): 
                        $childCapacityPercent = $child['capacity_limit'] ? min(100, ($child['item_count'] / $child['capacity_limit']) * 100) : 0;
                    ?>
                        <div class="tree-item child">
                            <div class="location-header">
                                <div>
                                    <span class="location-name"><?php echo htmlspecialchars($child['name']); ?></span>
                                    <span class="location-type type-<?php echo $child['location_type']; ?>">
                                        <?php echo str_replace('_', ' ', $child['location_type']); ?>
                                    </span>
                                    <?php if ($child['climate_controlled']): ?>
                                        <span style="color: #007bff;">❄️</span>
                                    <?php endif; ?>
                                    <span class="security-indicator security-<?php echo $child['security_level']; ?>">
                                        <?php echo $securityIcons[$child['security_level']]; ?>
                                    </span>
                                </div>
                                <span class="item-count"><?php echo $child['item_count']; ?> items</span>
                            </div>
                            
                            <?php if ($child['description']): ?>
                                <div class="location-meta"><?php echo htmlspecialchars($child['description']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($child['capacity_limit']): ?>
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo $childCapacityPercent; %>%"></div>
                                </div>
                                <div class="location-meta">
                                    Capacity: <?php echo $child['item_count']; ?> / <?php echo $child['capacity_limit']; ?> 
                                    (<?php echo round($childCapacityPercent, 1); ?>%)
                                </div>
                            <?php endif; ?>
                            
                            <div class="location-actions">
                                <button class="btn btn-primary" onclick="editLocation(<?php echo $child['id']; ?>)">✏️ Edit</button>
                                <button class="btn btn-warning" onclick="viewItems(<?php echo $child['id']; ?>)">👁️ View Items</button>
                                <?php if ($child['item_count'] == 0): ?>
                                    <button class="btn btn-danger" onclick="deleteLocation(<?php echo $child['id']; ?>)">🗑️ Delete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="location-stats">
                <h3 style="margin-bottom: 1.5rem;">Quick Actions</h3>
                
                <div style="margin-bottom: 2rem;">
                    <h4>Location Templates</h4>
                    <p style="color: #666; margin-bottom: 1rem;">Quickly create common storage locations:</p>
                    
                    <button class="btn btn-secondary" onclick="createTemplate('home_setup')" style="width: 100%; margin-bottom: 0.5rem;">
                        🏠 Standard Home Setup
                    </button>
                    <button class="btn btn-secondary" onclick="createTemplate('collector_setup')" style="width: 100%; margin-bottom: 0.5rem;">
                        🎯 Serious Collector Setup
                    </button>
                    <button class="btn btn-secondary" onclick="createTemplate('apartment_setup')" style="width: 100%; margin-bottom: 1rem;">
                        🏢 Apartment/Small Space
                    </button>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h4>Capacity Analysis</h4>
                    <?php
                    $totalCapacity = array_sum(array_column($locations, 'capacity_limit'));
                    $totalItems = array_sum(array_column($locations, 'item_count'));
                    $overCapacity = array_filter($locations, function($l) { 
                        return $l['capacity_limit'] && $l['item_count'] > $l['capacity_limit']; 
                    });
                    ?>
                    
                    <div class="location-stats-item">
                        <span>Total Capacity:</span>
                        <span><?php echo $totalCapacity ?: 'Unlimited'; ?></span>
                    </div>
                    <div class="location-stats-item">
                        <span>Items Stored:</span>
                        <span><?php echo $totalItems; ?></span>
                    </div>
                    <div class="location-stats-item">
                        <span>Utilization:</span>
                        <span><?php echo $totalCapacity ? round(($totalItems / $totalCapacity) * 100, 1) . '%' : 'N/A'; ?></span>
                    </div>
                    
                    <?php if (count($overCapacity) > 0): ?>
                        <div style="color: #dc3545; margin-top: 1rem;">
                            ⚠️ <?php echo count($overCapacity); ?> location(s) over capacity
                        </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h4>Security Overview</h4>
                    <?php
                    $securityCounts = array_count_values(array_column($locations, 'security_level'));
                    ?>
                    <?php foreach ($securityCounts as $level => $count): ?>
                        <div class="location-stats-item">
                            <span><?php echo ucfirst($level); ?>:</span>
                            <span><?php echo $count; ?> location(s)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Location Modal -->
    <div id="location-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Add Storage Location</h3>
            
            <form id="location-form" method="post">
                <input type="hidden" name="action" value="add_location">
                <input type="hidden" name="location_id" id="location_id">
                
                <div class="form-group">
                    <label for="name">Location Name *</label>
                    <input type="text" name="name" id="name" required placeholder="e.g., Living Room Bookshelf">
                </div>
                
                <div class="form-group">
                    <label for="location_type">Location Type *</label>
                    <select name="location_type" id="location_type" required>
                        <option value="room">Room</option>
                        <option value="shelf">Shelf</option>
                        <option value="cabinet">Cabinet</option>
                        <option value="box">Box</option>
                        <option value="storage_unit">Storage Unit</option>
                        <option value="offsite">Offsite</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="parent_location_id">Parent Location (Optional)</label>
                    <select name="parent_location_id" id="parent_location_id">
                        <option value="">None (Top Level)</option>
                        <?php foreach ($parentLocations as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>">
                                <?php echo htmlspecialchars($parent['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="2" placeholder="Brief description of this location"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="address">Address (for offsite locations)</label>
                    <textarea name="address" id="address" rows="2" placeholder="Full address if stored offsite"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="access_notes">Access Notes</label>
                    <textarea name="access_notes" id="access_notes" rows="2" placeholder="Special instructions for accessing items"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="capacity_limit">Capacity Limit</label>
                    <input type="number" name="capacity_limit" id="capacity_limit" min="1" placeholder="Maximum number of items">
                </div>
                
                <div class="form-group">
                    <label for="security_level">Security Level</label>
                    <select name="security_level" id="security_level">
                        <option value="none">None</option>
                        <option value="basic" selected>Basic</option>
                        <option value="high">High</option>
                        <option value="vault">Vault</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="climate_controlled" id="climate_controlled">
                        <label for="climate_controlled">Climate Controlled</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Location</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Move Items Modal -->
    <div id="move-modal" class="modal">
        <div class="modal-content">
            <h3>Move Items Between Locations</h3>
            
            <form method="post">
                <input type="hidden" name="action" value="move_items">
                
                <div class="form-group">
                    <label for="from_location_id">From Location</label>
                    <select name="from_location_id" id="from_location_id" required>
                        <option value="">Select Source Location</option>
                        <?php foreach ($locations as $location): ?>
                            <?php if ($location['item_count'] > 0): ?>
                                <option value="<?php echo $location['id']; ?>">
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo $location['item_count']; ?> items)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="to_location_id">To Location</label>
                    <select name="to_location_id" id="to_location_id" required>
                        <option value="">Select Destination Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['id']; ?>">
                                <?php echo htmlspecialchars($location['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeMoveModal()">Cancel</button>
                    <button type="submit" class="btn btn-warning">Move All Items</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Storage Location';
            document.getElementById('location-form').reset();
            document.querySelector('input[name="action"]').value = 'add_location';
            document.getElementById('location-modal').style.display = 'block';
        }

        function editLocation(id) {
            // Implementation for editing location
            document.getElementById('modal-title').textContent = 'Edit Storage Location';
            document.querySelector('input[name="action"]').value = 'update_location';
            document.getElementById('location_id').value = id;
            document.getElementById('location-modal').style.display = 'block';
            
            // Load location data (would typically fetch from API)
            // For now, just show the modal
        }

        function deleteLocation(id) {
            if (confirm('Are you sure you want to delete this location?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_location">
                    <input type="hidden" name="location_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewItems(locationId) {
            window.open(`../public/index.php?location=${locationId}`, '_blank');
        }

        function closeModal() {
            document.getElementById('location-modal').style.display = 'none';
        }

        function openMoveModal() {
            document.getElementById('move-modal').style.display = 'block';
        }

        function closeMoveModal() {
            document.getElementById('move-modal').style.display = 'none';
        }

        function createTemplate(type) {
            if (confirm(`Create ${type.replace('_', ' ')} locations?`)) {
                // Implementation for creating location templates
                alert(`Creating ${type} template...`);
            }
        }

        function generateLocationReport() {
            window.open('../api/location_report.php', '_blank');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const locationModal = document.getElementById('location-modal');
            const moveModal = document.getElementById('move-modal');
            
            if (event.target === locationModal) {
                closeModal();
            }
            if (event.target === moveModal) {
                closeMoveModal();
            }
        }
    </script>
</body>
</html>