/**
 * Desa Wisata Core Admin Scripts
 * Path: assets/js/admin-scripts.js
 * * Menangani interaksi UI dan AJAX Request dengan token keamanan (Nonce).
 * Version: 2.5.0 (Security Update)
 */
jQuery(document).ready(function($) {
    'use strict';

    // Inisialisasi Select2 jika ada elemennya
    if ($('.dw-select2-user').length) {
        $('.dw-select2-user').select2({
            ajax: {
                url: dw_vars.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'dw_search_user',
                        search: params.term,
                        page: params.page || 1,
                        security: dw_vars.nonce // REQUIRED: Send Nonce
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.results,
                            pagination: {
                                more: data.data.pagination.more
                            }
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            placeholder: 'Cari User (Nama/Email)...',
            minimumInputLength: 3
        });
    }

    /**
     * Handler Generik untuk Tombol Aksi (Edit, Hapus, Verifikasi)
     * Menggunakan Event Delegation untuk elemen dinamis
     */
    $(document).on('click', '.dw-action-btn', function(e) {
        e.preventDefault();
        
        let btn = $(this);
        let action = btn.data('action');
        let id = btn.data('id');
        let ajaxAction = '';
        let confirmText = '';

        // Tentukan aksi AJAX dan teks konfirmasi berdasarkan tombol
        switch(action) {
            case 'verify':
                ajaxAction = 'dw_verifikasi_pedagang';
                confirmText = dw_vars.strings.confirm_verify;
                break;
            case 'reject':
                ajaxAction = 'dw_reject_pedagang';
                confirmText = dw_vars.strings.confirm_reject;
                break;
            case 'delete':
                ajaxAction = 'dw_delete_pedagang';
                confirmText = dw_vars.strings.confirm_delete;
                break;
            case 'edit':
                openEditModal(id);
                return; // Stop di sini, handler edit terpisah
            default:
                return;
        }

        // Konfirmasi SweetAlert2
        Swal.fire({
            title: 'Konfirmasi',
            text: confirmText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Lanjutkan!'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading();
                
                $.ajax({
                    url: dw_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        id: id,
                        security: dw_vars.nonce // REQUIRED: Send Nonce
                    },
                    success: function(response) {
                        Swal.close(); // Tutup loading
                        if (response.success) {
                            Swal.fire('Berhasil!', response.data.message, 'success')
                                .then(() => {
                                    location.reload(); // Reload table
                                });
                        } else {
                            Swal.fire('Gagal!', response.data.message || dw_vars.strings.error, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                    }
                });
            }
        });
    });

    // Fungsi membuka modal edit
    function openEditModal(id) {
        showLoading();
        $.ajax({
            url: dw_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dw_get_pedagang_detail',
                id: id,
                security: dw_vars.nonce // REQUIRED
            },
            success: function(response) {
                Swal.close();
                if (response.success) {
                    populateModal(response.data);
                    $('#dw-modal-pedagang').show(); // Asumsi modal jQuery standard atau custom CSS
                } else {
                    Swal.fire('Gagal', response.data.message, 'error');
                }
            }
        });
    }

    // Helper: Tampilkan Loading
    function showLoading() {
        Swal.fire({
            title: 'Memproses...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    // Handle Form Submit (Simpan/Edit Pedagang)
    $('#dw-form-pedagang').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'dw_save_pedagang');
        formData.append('security', dw_vars.nonce); // REQUIRED

        showLoading();

        $.ajax({
            url: dw_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire('Berhasil!', response.data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal!', response.data.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error!', 'Gagal menghubungi server.', 'error');
            }
        });
    });

    // Close Modal Handler
    $('.dw-modal-close').on('click', function() {
        $(this).closest('.dw-modal').hide();
    });

    // Helper populate modal (Sederhana)
    function populateModal(data) {
        $('#dw_pedagang_id').val(data.id);
        $('#dw_nama_pemilik').val(data.nama_pemilik);
        $('#dw_nama_toko').val(data.nama_toko);
        $('#dw_status').val(data.status);
        
        // Handle select2 user pre-fill
        if ($('.dw-select2-user').length && data.user_id) {
            let option = new Option(data.user_name, data.user_id, true, true);
            $('.dw-select2-user').append(option).trigger('change');
        }
    }

});