<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> Student Manager</p>
    <p>PHP + MySQL</p>
</footer>

<!-- Delete confirmation modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Xác nhận xóa</h3>
        <p id="deleteModalMsg">Bạn có chắc muốn xóa không?</p>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="btn btn-secondary">Hủy</button>
            <a id="deleteModalConfirm" href="#" class="btn btn-danger">Xóa</a>
        </div>
    </div>
</div>
<script>
function confirmDelete(url, msg) {
    document.getElementById('deleteModalMsg').textContent = msg || 'Bạn có chắc muốn xóa không?';
    document.getElementById('deleteModalConfirm').href = url;
    document.getElementById('deleteModal').style.display = 'block';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>