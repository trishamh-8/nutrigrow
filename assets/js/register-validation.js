document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const roleSelect = document.querySelector('select[name="role"]');
    const sertifikasiField = document.querySelector('#sertifikasi-container');
    const sertifikasiInput = document.querySelector('input[name="sertifikasi"]');

    // Show/hide sertifikasi field and handle admin code based on role selection
    roleSelect.addEventListener('change', function() {
        const selectedRole = this.value;
        
        // Handle sertifikasi field
        if (selectedRole === 'tenaga_kesehatan') {
            sertifikasiField.style.display = 'block';
            sertifikasiInput.required = true;
        } else {
            sertifikasiField.style.display = 'none';
            sertifikasiInput.required = false;
        }

        // Handle admin code field
        const adminCodeContainer = document.getElementById('admin-code-container');
        if (adminCodeContainer) {
            if (selectedRole === 'admin') {
                adminCodeContainer.style.display = 'block';
                document.getElementById('admin_code').required = true;
            } else {
                adminCodeContainer.style.display = 'none';
                document.getElementById('admin_code').required = false;
            }
        }
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const selectedRole = roleSelect.value;

        // Validate required fields
        const requiredFields = form.querySelectorAll('input[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                showError(field, 'Field ini harus diisi');
            } else {
                clearError(field);
            }
        });

        // Special validation for healthcare worker
        if (selectedRole === 'tenaga_kesehatan' && !sertifikasiInput.value.trim()) {
            isValid = false;
            showError(sertifikasiInput, 'Nomor sertifikasi wajib diisi untuk tenaga kesehatan');
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    function showError(element, message) {
        // Clear existing error
        clearError(element);
        
        // Create and show error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.color = 'red';
        errorDiv.style.fontSize = '0.8em';
        errorDiv.style.marginTop = '5px';
        errorDiv.textContent = message;
        
        element.parentNode.appendChild(errorDiv);
        element.style.borderColor = 'red';
    }

    function clearError(element) {
        const parent = element.parentNode;
        const errorDiv = parent.querySelector('.error-message');
        if (errorDiv) {
            parent.removeChild(errorDiv);
        }
        element.style.borderColor = '';
    }
});