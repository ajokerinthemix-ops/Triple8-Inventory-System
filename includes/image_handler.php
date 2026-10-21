<?php
// Image Upload Handler for Product Images
class ImageHandler {
    private $maxFileSize;
    private $allowedTypes;
    private $uploadDir;
    private $errors = [];
    
    public function __construct() {
        $this->maxFileSize = 2048 * 1024; // 2MB default
        $this->allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $this->uploadDir = '../uploads/products/';
    }
    
    public function uploadImage($file, $productId = null) {
        if (!$this->validateImage($file)) {
            return ['success' => false, 'error' => implode(', ', $this->errors)];
        }
        
        $fileName = $this->generateFileName($file['name']);
        $filePath = $this->uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Skip image resizing since GD library is not available
            // Images will be stored at original size
            
            return [
                'success' => true,
                'file_name' => $fileName,
                'file_path' => 'uploads/products/' . $fileName,
                'full_path' => $filePath
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
    
    private function validateImage($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = 'No file uploaded';
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $this->errors[] = 'File size too large. Maximum: ' . ($this->maxFileSize / 1024) . 'KB';
        }
        
        // Check file type
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, $this->allowedTypes)) {
            $this->errors[] = 'Invalid file type. Allowed: ' . implode(', ', $this->allowedTypes);
        }
        
        // Check if it's actually an image
        if (!getimagesize($file['tmp_name'])) {
            $this->errors[] = 'File is not a valid image';
        }
        
        return empty($this->errors);
    }
    
    private function generateFileName($originalName) {
        $fileInfo = pathinfo($originalName);
        $extension = strtolower($fileInfo['extension']);
        return uniqid('product_', true) . '.' . $extension;
    }
    
    public function deleteImage($imagePath) {
        $fullPath = '../' . $imagePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    public function getImageUrl($imagePath) {
        if (empty($imagePath) || !file_exists(__DIR__ . '/../' . $imagePath)) {
            return 'assets/images/triple8_logo.png';
        }
        return $imagePath;
    }
}

// Function to handle multiple image uploads
function handleProductImages($productId) {
    if (!isset($_FILES['product_images'])) {
        return ['success' => false, 'error' => 'No images uploaded'];
    }
    
    $handler = new ImageHandler();
    $uploadedImages = [];
    $errors = [];
    
    foreach ($_FILES['product_images']['name'] as $key => $name) {
        if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        $file = [
            'name' => $_FILES['product_images']['name'][$key],
            'type' => $_FILES['product_images']['type'][$key],
            'tmp_name' => $_FILES['product_images']['tmp_name'][$key],
            'error' => $_FILES['product_images']['error'][$key],
            'size' => $_FILES['product_images']['size'][$key]
        ];
        
        $result = $handler->uploadImage($file, $productId);
        
        if ($result['success']) {
            $uploadedImages[] = $result;
        } else {
            $errors[] = $result['error'];
        }
    }
    
    return [
        'success' => !empty($uploadedImages),
        'images' => $uploadedImages,
        'errors' => $errors
    ];
}
?>
