<?php
/**
 * File Name:   includes/dw-logic-updates.php
 * Description: Logika Inti untuk Relasi Otomatis berdasarkan Wilayah dan Sistem Komisi Berjenjang,
 * serta penanganan update struktur database.
 */

if (!defined('ABSPATH')) exit;

/**
 * Class DW_Logic_Updates
 * Menangani logika bisnis inti plugin.
 */
class DW_Logic_Updates {

    /**
     * Inisialisasi hook WordPress
     */
    public static function init() {
        // Trigger saat pedagang disimpan atau diperbarui untuk mengecek relasi desa
        add_action('save_post_pedagang', [__CLASS__, 'sync_pedagang_relation'], 10, 3);
        
        // Trigger saat desa wisata baru mendaftar untuk mengklaim pedagang independen di wilayahnya
        add_action('save_post_desa_wisata', [__CLASS__, 'sync_new_desa_to_merchants'], 10, 3);

        // Hook untuk menjalankan update database struktur (dbDelta perbaikan)
        add_action('admin_init', [__CLASS__, 'trigger_database_updates']);
    }

    /**
     * Sinkronisasi Pedagang ke Desa berdasarkan Kelurahan
     */
    public static function sync_pedagang_relation($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'pedagang') return;

        $kelurahan = get_post_meta($post_id, '_kelurahan', true);
        if (empty($kelurahan)) return;

        $desas = get_posts([
            'post_type' => 'desa_wisata',
            'meta_query' => [
                [
                    'key' => '_kelurahan',
                    'value' => $kelurahan,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($desas)) {
            $desa_id = $desas[0]->ID;
            update_post_meta($post_id, '_desa_id', $desa_id);
            update_post_meta($post_id, '_is_independent', 'no');
        } else {
            update_post_meta($post_id, '_desa_id', '');
            update_post_meta($post_id, '_is_independent', 'yes');
        }
    }

    /**
     * Menghubungkan Pedagang Independen ke Desa Baru
     */
    public static function sync_new_desa_to_merchants($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'desa_wisata') return;

        $kelurahan = get_post_meta($post_id, '_kelurahan', true);
        if (empty($kelurahan)) return;

        $pedagangs = get_posts([
            'post_type' => 'pedagang',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_kelurahan',
                    'value' => $kelurahan,
                    'compare' => '='
                ],
                [
                    'key' => '_is_independent',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ]
        ]);

        foreach ($pedagangs as $p) {
            update_post_meta($p->ID, '_desa_id', $post_id);
            update_post_meta($p->ID, '_is_independent', 'no');
        }
    }

    /**
     * Logika Perhitungan Komisi untuk Desa
     */
    public static function calculate_desa_commission($pedagang_id, $paket_id, $total_amount) {
        $approved_by = get_post_meta($pedagang_id, '_approved_by', true); 
        $desa_id = get_post_meta($pedagang_id, '_desa_id', true);

        if (empty($desa_id) || $approved_by !== 'desa') {
            return 0;
        }

        $percentage = get_post_meta($paket_id, '_commission_percentage', true);
        if (!$percentage || $percentage <= 0) return 0;

        return ($percentage / 100) * $total_amount;
    }

    /**
     * Trigger update database jika diperlukan
     * Menjalankan perbaikan struktur tabel yang sebelumnya error
     */
    public static function trigger_database_updates() {
        $target_version = '1.0.3'; // Versi dinaikkan untuk memastikan update jalan
        $current_version = get_option('dw_logic_db_version', '0');

        if (version_compare($current_version, $target_version, '<')) {
            self::perform_database_repair();
            update_option('dw_logic_db_version', $target_version);
        }
    }

    /**
     * Eksekusi perbaikan database (SQL Fixes)
     * Menggantikan logika dbDelta yang error sebelumnya
     */
    private static function perform_database_repair() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. PERBAIKAN TABEL OJEK (Filter Wilayah)
        $table_ojek = $wpdb->prefix . 'dw_ojek';
        $wilayah_columns = [
            'provinsi'      => 'VARCHAR(100)',
            'kota'          => 'VARCHAR(100)',
            'kecamatan'     => 'VARCHAR(100)',
            'kelurahan'     => 'VARCHAR(100)',
            'kodepos'       => 'VARCHAR(10)'
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_ojek'") == $table_ojek) {
            foreach ($wilayah_columns as $col => $type) {
                $existing_col = $wpdb->get_results("SHOW COLUMNS FROM $table_ojek LIKE '$col'");
                if (empty($existing_col)) {
                    // Query bersih tanpa komentar SQL yang menyebabkan error
                    $wpdb->query("ALTER TABLE $table_ojek ADD COLUMN $col $type");
                }
            }
        }

        // 2. PERBAIKAN TABEL TRANSAKSI (ENUM Status)
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") == $table_transaksi) {
            $statuses = [
                'menunggu_pembayaran', 'pembayaran_dikonfirmasi', 'pembayaran_gagal',
                'diproses', 'dikirim', 'selesai', 'dibatalkan', 'refunded',
                'menunggu_driver', 'penawaran_driver', 'nego',
                'menunggu_penjemputan', 'dalam_perjalanan'
            ];
            
            // Susun string ENUM dengan benar: 'val1','val2'
            $enum_string = "'" . implode("','", $statuses) . "'";
            
            // Pastikan tidak ada syntax error pada ALTER TABLE
            $wpdb->query("ALTER TABLE $table_transaksi CHANGE COLUMN status_transaksi status_transaksi ENUM($enum_string) DEFAULT 'menunggu_pembayaran'");
        }

        // 3. PERBAIKAN TABEL REVOKED TOKENS (Duplicate Key Check)
        $table_tokens = $wpdb->prefix . 'dw_revoked_tokens';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_tokens'") == $table_tokens) {
            $index_exists = $wpdb->get_var("SHOW KEYS FROM $table_tokens WHERE Key_name = 'token_hash'");
            if (!$index_exists) {
                $wpdb->query("ALTER TABLE $table_tokens ADD INDEX token_hash (token_hash)");
            }
        }
    }
}

// Jalankan inisialisasi logika
DW_Logic_Updates::init();