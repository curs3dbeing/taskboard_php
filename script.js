
document.addEventListener('DOMContentLoaded', function() {

    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordInput = document.getElementById('password');
    
    if (confirmPasswordInput && passwordInput) {
        function validatePasswordMatch() {
            if (confirmPasswordInput.value !== passwordInput.value) {
                confirmPasswordInput.setCustomValidity('Пароли не совпадают');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }
        
        passwordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
    

    const emailInput = document.getElementById('email');
    if (emailInput && emailInput.type === 'text') {
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.setCustomValidity('Пожалуйста, введите реальную почту');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    

    const usernameInput = document.getElementById('username');
    if (usernameInput && usernameInput.hasAttribute('minlength')) {
        usernameInput.addEventListener('input', function() {
            const minLength = parseInt(this.getAttribute('minlength'));
            const maxLength = parseInt(this.getAttribute('maxlength'));
            if (this.value.length < minLength) {
                this.setCustomValidity(`Логин должен состоять как минимум из ${minLength} символов`);
            } else if (this.value.length > maxLength) {
                this.setCustomValidity(`Логин должен быть меньше чем ${maxLength} символов в длину`);
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

function openTaskModal(taskId = null, taskName = '', taskDescription = '', priority = 2) {
    const modal = document.getElementById('taskModal');
    const form = document.getElementById('taskForm');
    const modalTitle = document.getElementById('modalTitle');
    const taskIdInput = document.getElementById('taskId');
    const taskNameInput = document.getElementById('taskName');
    const taskDescriptionInput = document.getElementById('taskDescription');
    const taskPriorityInput = document.getElementById('taskPriority');
    
    if (taskId) {
        modalTitle.textContent = 'Редактировать задачу';
        taskIdInput.value = taskId;
        taskNameInput.value = taskName;
        taskDescriptionInput.value = taskDescription;
        taskPriorityInput.value = priority;
    } else {
        modalTitle.textContent = 'Новая задача';
        taskIdInput.value = '';
        taskNameInput.value = '';
        taskDescriptionInput.value = '';
        taskPriorityInput.value = '2';
        form.reset();
    }
    
    modal.classList.add('show');
    taskNameInput.focus();
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    modal.classList.remove('show');
    const form = document.getElementById('taskForm');
    form.reset();
}

window.onclick = function(event) {
    const modal = document.getElementById('taskModal');
    if (event.target === modal) {
        closeTaskModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTaskModal();
    }
});

function editTask(id, name, description, priority) {
    openTaskModal(id, name, description, priority);
}


function switchTab(tabName) {

    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    

    document.getElementById('content-' + tabName).classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}


function toggleTask(taskId, completed) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'toggle_task.php';
    
    const taskIdInput = document.createElement('input');
    taskIdInput.type = 'hidden';
    taskIdInput.name = 'task_id';
    taskIdInput.value = taskId;
    
    const completedInput = document.createElement('input');
    completedInput.type = 'hidden';
    completedInput.name = 'completed';
    completedInput.value = completed;
    
    form.appendChild(taskIdInput);
    form.appendChild(completedInput);
    document.body.appendChild(form);
    form.submit();
}

function deleteTask(id) {
    if (confirm('Вы уверены что хотите удалить задачу?')) {
        window.location.href = 'delete_task.php?id=' + id;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

