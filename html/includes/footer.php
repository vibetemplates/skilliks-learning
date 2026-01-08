<?php
/**
 * Footer Include
 * 
 * Common footer for all pages
 */
?>

<footer class="mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">SkillikS © <?php echo date('Y'); ?> Kinetic Seas Inc</span>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- HTMX Configuration -->
<script>
    // Configure HTMX to show loading indicators
    document.body.addEventListener('htmx:beforeRequest', function(evt) {
        // Add loading class to the element
        evt.detail.target.classList.add('htmx-loading');
    });
    
    document.body.addEventListener('htmx:afterRequest', function(evt) {
        // Remove loading class from the element
        evt.detail.target.classList.remove('htmx-loading');
    });
    
    // Add CSS for loading state
    const style = document.createElement('style');
    style.textContent = `
        .htmx-loading {
            position: relative;
            opacity: 0.6;
        }
        .htmx-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 2rem;
            height: 2rem;
            border: 0.2rem solid #0d6efd;
            border-radius: 50%;
            border-top-color: transparent;
            animation: htmx-spin 0.6s linear infinite;
        }
        @keyframes htmx-spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .htmx-indicator {
            opacity: 0;
            transition: opacity 200ms ease-in;
        }
        .htmx-request .htmx-indicator {
            opacity: 1;
        }
    `;
    document.head.appendChild(style);
</script>

<!-- Global JavaScript Functions -->
<script>
// Show notification function used by various components
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px; max-width: 400px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Flash messages from PHP
<?php 
$flashTypes = ['success', 'error', 'warning', 'info'];
foreach ($flashTypes as $type):
    if ($message = getFlashMessage($type)): 
?>
    document.addEventListener('DOMContentLoaded', function() {
        showNotification('<?php echo addslashes($message); ?>', '<?php echo $type; ?>');
    });
<?php 
    endif;
endforeach; 
?>
</script>

</body>
</html>