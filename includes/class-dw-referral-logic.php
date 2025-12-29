<?php
/**
 * Class DW_Referral_Logic
 * Logic inti untuk melacak pemilik kode referral di database v3.7
 * Menangani pencarian pemilik kode di tabel Desa, Verifikator, dan Pedagang.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Logic {

    /**
     * Mencari pemilik kode referral.
     * @param string $code Kode yang diinput user.
     * @return array|false Array data pemilik atau false jika tidak ketemu.
     */
    public static function resolve_owner( $code ) {
        global $wpdb;

        if ( empty( $code ) ) return false;

        // 1. Cek Tabel Desa
        $desa = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user_desa, nama_desa FROM {$wpdb->prefix}dw_desa WHERE kode_referral = %s AND status = 'aktif'", $code ) );
        if ( $desa ) {
            return [
                'type' => 'desa',
                'id'   => $desa->id,
                'user_id' => $desa->id_user_desa,
                'name' => $desa->nama_desa
            ];
        }

        // 2. Cek Tabel Verifikator
        $verif = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user, nama_lengkap FROM {$wpdb->prefix}dw_verifikator WHERE kode_referral = %s AND status = 'aktif'", $code ) );
        if ( $verif ) {
            return [
                'type' => 'verifikator',
                'id'   => $verif->id,
                'user_id' => $verif->id_user,
                'name' => $verif->nama_lengkap
            ];
        }

        // 3. Cek Tabel Pedagang (Untuk referral ke pembeli atau sesama pedagang jika ada logic MLM)
        $pedagang = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE kode_referral_saya = %s AND status_akun = 'aktif'", $code ) );
        if ( $pedagang ) {
            return [
                'type' => 'pedagang',
                'id'   => $pedagang->id,
                'user_id' => $pedagang->id_user,
                'name' => $pedagang->nama_toko
            ];
        }

        return false;
    }

    /**
     * Menghitung Komisi
     */
    public static function calculate_commission( $paket_id, $harga_paket ) {
        global $wpdb;
        $paket = $wpdb->get_row( $wpdb->prepare( "SELECT komisi_nominal, persentase_komisi_desa FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d", $paket_id ) );

        if ( ! $paket ) return 0;

        // Prioritas: Nominal Rupiah
        if ( ! empty( $paket->komisi_nominal ) && $paket->komisi_nominal > 0 ) {
            return floatval( $paket->komisi_nominal );
        }

        // Fallback: Persentase
        if ( ! empty( $paket->persentase_komisi_desa ) && $paket->persentase_komisi_desa > 0 ) {
            return ( floatval( $paket->persentase_komisi_desa ) / 100 ) * $harga_paket;
        }

        return 0;
    }

    /**
     * Mencatat Riwayat Komisi & Update Saldo (Untuk Desa / Verifikator)
     */
    public static function distribute_commission( $purchase_id, $pedagang_id ) {
        global $wpdb;

        // 1. Cek Pedagang daftar lewat siapa
        $pedagang = $wpdb->get_row( $wpdb->prepare( "SELECT terdaftar_melalui_kode, id_verifikator, id_desa FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pedagang_id ) );
        
        // Jika tidak ada kode referral, komisi masuk Admin (System default, tidak dicatat di tabel komisi referral)
        if ( ! $pedagang || empty( $pedagang->terdaftar_melalui_kode ) ) return; 

        // 2. Cari pemilik kode
        $owner = self::resolve_owner( $pedagang->terdaftar_melalui_kode );
        if ( ! $owner ) return;

        // 3. Ambil data paket
        $pembelian = $wpdb->get_row( $wpdb->prepare( "SELECT id_paket, harga_paket FROM {$wpdb->prefix}dw_pembelian_paket WHERE id = %d", $purchase_id ) );
        if ( ! $pembelian ) return;

        // 4. Hitung
        $jumlah_komisi = self::calculate_commission( $pembelian->id_paket, $pembelian->harga_paket );
        if ( $jumlah_komisi <= 0 ) return;

        // 5. Catat Log
        $wpdb->insert(
            "{$wpdb->prefix}dw_riwayat_komisi",
            array(
                'id_penerima'        => $owner['id'],
                'role_penerima'      => $owner['type'],
                'id_sumber_pedagang' => $pedagang_id,
                'id_pembelian_paket' => $purchase_id,
                'jumlah_komisi'      => $jumlah_komisi,
                'keterangan'         => "Komisi paket dari {$owner['type']} code: {$pedagang->terdaftar_melalui_kode}"
            )
        );

        // 6. Update Saldo Wallet
        if ( $owner['type'] === 'desa' ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}dw_desa SET saldo_komisi = saldo_komisi + %f WHERE id = %d", $jumlah_komisi, $owner['id'] ) );
        } elseif ( $owner['type'] === 'verifikator' ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}dw_verifikator SET total_pendapatan_komisi = total_pendapatan_komisi + %f, saldo_saat_ini = saldo_saat_ini + %f WHERE id = %d", $jumlah_komisi, $jumlah_komisi, $owner['id'] ) );
        }
        
        // Update snapshot di pembelian
        $wpdb->update(
            "{$wpdb->prefix}dw_pembelian_paket",
            ['komisi_nominal_cair' => $jumlah_komisi],
            ['id' => $purchase_id]
        );
    }
}