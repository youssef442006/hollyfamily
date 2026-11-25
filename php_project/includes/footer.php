    </main>
</div>
</div>

<!-- SweetAlert2 (Local Ultra Fast) -->
<script src="assets/js/sweetalert2.all.min.js"></script>

<script>
    // Toggle Mobile Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar && backdrop) {
            sidebar.classList.toggle('translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    }

    // Generic Delete Confirmation
    function confirmDelete(url, title = 'هل أنت متأكد من الحذف؟', text = 'لا يمكن التراجع عن هذه العملية!') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#15264a',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }

    function postDelete(actionUrl, id, csrfToken, title = 'هل أنت متأكد من الحذف؟', text = 'لا يمكن التراجع عن هذه العملية!') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#15264a',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'delete_id';
                inputId.value = id;
                form.appendChild(inputId);
                const inputCsrf = document.createElement('input');
                inputCsrf.type = 'hidden';
                inputCsrf.name = 'csrf_token';
                inputCsrf.value = csrfToken;
                form.appendChild(inputCsrf);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
</body>
</html>
