<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

use App\Http\Traits\TraitJWTRsiMadinah;
use App\Http\Traits\BPJS\AntrianTrait;


use App\Models\User;
use Exception;

class AntrolBPJSController extends Controller
{
    use TraitJWTRsiMadinah, AntrianTrait;
    /////////////
    // API SIMRS
    /////////////

    public function token(Request $request)
    {
        $credentials = [
            'name' => $request->header('x-username'),
            'password' => $request->header('x-password')
        ];

        if (Auth::attempt($credentials)) {
            $token = $this->createToken($credentials['name'], $credentials['password']);
            $data['token'] =  $token;
            return $this->sendResponse($request, $data, 200);
        } else {
            return $this->sendError($request, "Unauthorized (Username dan Password Salah)", 401);
        }
    }

    // Jadwal operasi///////////////////////////////
    public function jadwaloperasirs(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }
                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "tanggalawal" => "required|date|date_format:Y-m-d",
                    "tanggalakhir" => "required|date|date_format:Y-m-d|after:tanggalawal",
                ]);
                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  201);
                }

                $request['tanggalawal'] = Carbon::parse($request->tanggalawal)->startOfDay();
                $request['tanggalakhir'] = Carbon::parse($request->tanggalakhir)->endOfDay();

                $jadwalops = DB::table('booking_operasi')
                    ->whereBetween('tanggal', [$request->tanggalawal, $request->tanggalakhir])
                    ->get();
                // if data kosong
                if (!$jadwalops->count()) {
                    return $this->sendError($request, 'Data Tidak ditemukan',);
                }

                $jadwals = [];
                foreach ($jadwalops as  $jadwalop) {
                    $jadwals[] = [
                        "kodebooking" => $jadwalop->no_rawat,
                        "tanggaloperasi" => Carbon::parse($jadwalop->tanggal)->format('Y-m-d'),
                        "jenistindakan" => $jadwalop->nm_paket,
                        "kodepoli" =>  $jadwalop->POLI_ID ?? 'BED',
                        // "namapoli" => $jadwalop->ruangan_asal,
                        "namapoli" => 'BEDAH',
                        "terlaksana" => $jadwalop->status == 'Menunggu' ? 0 : 1,
                        "nopeserta" => $jadwalop->no_peserta,
                        "lastupdate" => now()->timestamp * 1000,
                    ];
                }
                $response = [
                    "list" => $jadwals
                ];
                return $this->sendResponse($request, $response, 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    public function jadwaloperasipasien(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }

                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "nopeserta" => "required|digits:13",
                ]);
                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  201);
                }


                $jadwalops = DB::table('booking_operasi')
                    ->where('no_peserta', [$request->nopeserta])
                    ->get();

                // if data kosong
                if (!$jadwalops->count()) {
                    return $this->sendError($request, 'Data Tidak ditemukan',);
                }

                $jadwals = [];
                foreach ($jadwalops as  $jadwalop) {
                    $jadwals[] = [
                        "kodebooking" => $jadwalop->no_rawat,
                        "tanggaloperasi" => Carbon::parse($jadwalop->tanggal)->format('Y-m-d'),
                        "jenistindakan" => $jadwalop->nm_paket,
                        "kodepoli" =>  $jadwalop->POLI_ID ?? 'BED',
                        // "namapoli" => $jadwalop->ruangan_asal,
                        "namapoli" => 'BEDAH',
                        "terlaksana" => $jadwalop->status == 'Menunggu' ? 0 : 1,
                        "nopeserta" => $jadwalop->no_peserta,
                        "lastupdate" => now()->timestamp * 1000,
                    ];
                }
                $response = [
                    "list" => $jadwals
                ];
                return $this->sendResponse($request, $response, 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }
    // Jadwal operasi///////////////////////////////


    // JKN///////////////////////////////
    public function ambilantrean(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])

            ) {

                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }

                //proses data
                // Validator
                $rules = [
                    "nomorkartu" => "required|numeric|digits:13",
                    "nik" => "required|numeric|digits:16",
                    "nohp" => "required",
                    "kodepoli" => "required",
                    "norm" => "required",
                    "tanggalperiksa" => "required|date|date_format:Y-m-d",
                    "kodedokter" => "required",
                    "jampraktek" => "required",
                    "jeniskunjungan" => "required|numeric|between:1,4",
                    // "nomorreferensi" => "numeric",
                ];

                if ($request->jeniskunjungan != 2) {
                    $rules['nomorreferensi'] = "required";
                }

                $validator = Validator::make($request->all(), $rules);


                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(), 201);
                }

                // check tanggal backdate
                if (Carbon::parse($request->tanggalperiksa)->endOfDay()->isPast()) {
                    return $this->sendError($request, "Tanggal periksa sudah terlewat", 201);
                }
                // check tanggal hanya 7 hari
                if (Carbon::parse($request->tanggalperiksa) >  Carbon::now()->addDay(6)) {
                    return $this->sendError($request, "Antrian hanya dapat dibuat untuk 7 hari ke kedepan", 201);
                }

                // cek duplikasi nik antrian
                $antrian_nik = DB::table('referensi_mobilejkn_bpjs')
                    ->where('tanggalperiksa', $request->tanggalperiksa)
                    ->where('nik', $request->nik)
                    ->first();

                if ($antrian_nik) {
                    return $this->sendError($request, "Terdapat Antrian (" . $antrian_nik->nobooking . ") dengan nomor NIK yang sama pada tanggal tersebut yang belum selesai. Silahkan batalkan terlebih dahulu jika ingin mendaftarkan lagi.",  201);
                }

                // // cek pasien baru
                // $request['pasienbaru'] = 0;

                $pasien = DB::table('rsmst_pasiens')->where('nokartu_bpjs',  $request->nomorkartu)->first();

                if (empty($pasien)) {
                    return $this->sendError($request, "Nomor Kartu BPJS Pasien termasuk Pasien Baru di RSI Madinah. Silahkan daftar melalui pendaftaran offline",  201);
                }

                // cek no kartu sesuai tidak
                if ($pasien->nik_bpjs != $request->nik) {
                    return $this->sendError($request, "NIK anda yang terdaftar di BPJS dengan Di RSI Madinah berbeda. Silahkan perbaiki melalui pendaftaran offline",  201);
                }

                // cek dokter
                $kd_dr_bpjs = DB::table('rsmst_doctors')->where('kd_dr_bpjs',  $request->kodedokter ? $request->kodedokter : '')->get();

                if (!$kd_dr_bpjs->count()) {
                    return $this->sendError($request, "Dokter tidak ditemukan",  201);
                }

                // cek poli
                $kd_poli_bpjs = DB::table('rsmst_polis')->where('kd_poli_bpjs',  $request->kodepoli ? $request->kodepoli : '')->get();

                if (!$kd_poli_bpjs->count()) {
                    return $this->sendError($request, "Poli tidak ditemukan",  201);
                }

                $hari = strtoupper($this->hariIndo(Carbon::parse($request->tanggalperiksa)->dayName));
                $jammulai   = substr($request->jampraktek, 0, 5);
                $jamselesai = substr($request->jampraktek, 6, 5);

                // cek quota & hari
                $cekQuota = DB::table('scview_scpolis')
                    ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'dr_id', 'poli_desc', 'dr_name')
                    ->where('kd_poli_bpjs', $request->kodepoli)
                    ->where('kd_dr_bpjs', $request->kodedokter)
                    ->where('day_desc', $hari)
                    // ->where('mulai_praktek', $jammulai . ':00')
                    // ->where('selesai_praktek', $jamselesai . ':00')
                    ->first();

                $cekDaftar = DB::table('rsview_rjkasir')
                    ->select('rj_no')
                    ->where('kd_poli_bpjs', $request->kodepoli ? $request->kodepoli : '')
                    ->where('kd_dr_bpjs', $request->kodedokter ? $request->kodedokter : '')
                    ->where('rj_status', '!=', 'F')
                    ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $request->tanggalperiksa ? $request->tanggalperiksa : '')
                    ->get();


                // cek Quota tersedia
                $kuota = isset($cekQuota->kuota)
                    ? (($cekQuota->kuota)
                        ? $cekQuota->kuota
                        : 0)
                    : 0;

                if (!$kuota) {
                    return $this->sendError($request, "Pendaftaran ke Poli ini tidak tersedia",  201);
                }

                if ($cekQuota->kuota - $cekDaftar->count() == 0) {
                    return $this->sendError($request, "Quota tidak tersedia",  201);
                }


                // noUrutAntrian Booking (count all kecuali KRonis) if KR 999
                $sql = "select count(*) no_antrian
                        from rstxn_rjhdrs
                        where dr_id=:drId
                        and to_char(rj_date,'ddmmyyyy')=:tgl
                        and klaim_id!='KR'";
                // proses antrian
                $noUrutAntrian = DB::scalar($sql, [
                    "drId" => $cekQuota->dr_id,
                    "tgl" => Carbon::createFromFormat('Y-m-d', $request->tanggalperiksa)->format('dmY')
                ]);

                $sqlBooking = "select count(*) no_antrian
                        from referensi_mobilejkn_bpjs
                        where kodedokter=:drId
                        and tanggalperiksa=:tgl";
                // proses antrian
                $noUrutAntrianBooking = DB::scalar($sqlBooking, [
                    "drId" => $request->kodedokter,
                    "tgl" => $request->tanggalperiksa
                ]);

                $noAntrian = $noUrutAntrian + $noUrutAntrianBooking + 1;

                $tanggalperiksa = $request->tanggalperiksa . ' ' . $jammulai . ':00';
                $jadwalEstimasiTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksa)->addMinutes(10 * ($noAntrian + 1))->timestamp * 1000;

                $noBooking = Carbon::now()->format('YmdHis') . 'JKN';
                // tambah antrian database
                DB::table('referensi_mobilejkn_bpjs')
                    ->insert([
                        "nobooking" => $noBooking,
                        "no_rawat" => $noBooking,
                        "nomorkartu" => $request->nomorkartu,
                        "nik" => $request->nik,
                        "nohp" => $request->nohp,
                        "kodepoli" => $request->kodepoli,
                        "pasienbaru" => 0,
                        "norm" => $request->norm,
                        "tanggalperiksa" => $request->tanggalperiksa,
                        "kodedokter" => $request->kodedokter,
                        "jampraktek" => $request->jampraktek,
                        "jeniskunjungan" => $request->jeniskunjungan,
                        "nomorreferensi" => $request->nomorreferensi,
                        "nomorantrean" => $request->kodepoli . '-' . $noAntrian, //kerjakan ini
                        "angkaantrean" => $noAntrian,
                        "estimasidilayani" => $jadwalEstimasiTimestamp,
                        "sisakuotajkn" => $cekQuota->kuota - $cekDaftar->count(),
                        "kuotajkn" => $cekQuota->kuota,
                        "sisakuotanonjkn" => $cekQuota->kuota - $cekDaftar->count(),
                        "kuotanonjkn" => $cekQuota->kuota,
                        "status" => "Belum",
                        "validasi" => "",
                        "statuskirim" => "Belum",
                        "keterangan_batal" => "",
                        "tanggalbooking" => Carbon::now()->format('Y-m-d H:i:s'),
                        "daftardariapp" => "JKNMobileAPP",
                    ]);


                $response = [
                    "nomorantrean" => $request->kodepoli . '-' . $noAntrian, //kerjakan ini
                    "angkaantrean" => $noAntrian,
                    "kodebooking" => $noBooking,
                    "norm" =>  $request->norm,
                    "namapoli" => $cekQuota->poli_desc,
                    "namadokter" => $cekQuota->dr_name,
                    "estimasidilayani" => $jadwalEstimasiTimestamp,
                    "sisakuotajkn" => $cekQuota->kuota - $cekDaftar->count(),
                    "kuotajkn" => $cekQuota->kuota,
                    "sisakuotanonjkn" => $cekQuota->kuota - $cekDaftar->count(),
                    "kuotanonjkn" => $cekQuota->kuota,
                    "keterangan" => 'Peserta harap 60 menit lebih awal guna pencatatan administrasi',
                ];

                return $this->sendResponse($request, $response, 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    public function checkinantrean(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])

            ) {

                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }

                //proses data
                // Validator
                $rules = [
                    "kodebooking" => "required",
                    "waktu" => "required",
                ];

                $validator = Validator::make($request->all(), $rules);

                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(), 201);
                }

                $antrian = DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $request->kodebooking)
                    ->first();

                if (!$antrian) {
                    return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.",  201);
                }

                if (!Carbon::parse($antrian->tanggalperiksa)->isToday()) {
                    return $this->sendError($request, "Tanggal periksa bukan hari ini.", 201);
                }

                if ($antrian->status == 'Batal') {
                    return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
                }

                if ($antrian->status == 'Checkin') {
                    return $this->sendError($request, "Anda Sudah Checkin pada " . $antrian->validasi, 201);
                }

                // checkin +- 1jam
                $jammulai   = substr($antrian->jampraktek, 0, 5);
                // $jamselesai = substr($antrian->jampraktek, 6, 5);
                $tanggalperiksa = $antrian->tanggalperiksa . ' ' . $jammulai . ':00';
                $waktucheckin = Carbon::createFromTimestamp($request->waktu / 1000)->toDateTimeString();;

                $checkIn1Jam = Carbon::createFromFormat('Y-m-d H:i:s', $waktucheckin)->diffInHours(Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksa), false);

                // return ($checkIn1Jam . '  ' . $tanggalperiksa . '  ' . $waktucheckin);

                if ($checkIn1Jam < -1) {
                    return $this->sendError($request, "Lakukan Chekin 1 Jam Sebelum Pelayanan, Pelayanan dimulai " . $tanggalperiksa, 201);
                }

                if ($checkIn1Jam > 1) {
                    return $this->sendError($request, "Chekin Anda sudah expired " . $checkIn1Jam . " Jam yang lalu, Silahkan konfirmasi ke loket pendaftaran ", 201);
                }

                // cek Quota sebelum checkin
                $hari = strtoupper($this->hariIndo(Carbon::parse($tanggalperiksa)->dayName));

                $cekQuota = DB::table('scview_scpolis')
                    ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
                    ->where('kd_poli_bpjs', $antrian->kodepoli)
                    ->where('kd_dr_bpjs', $antrian->kodedokter)
                    ->where('day_desc', $hari)
                    // ->where('mulai_praktek', $jammulai . ':00')
                    // ->where('selesai_praktek', $jamselesai . ':00')
                    ->first();

                $cekDaftar = DB::table('rsview_rjkasir')
                    ->select('rj_no')
                    ->where('kd_poli_bpjs', $antrian->kodepoli ? $antrian->kodepoli : '')
                    ->where('kd_dr_bpjs', $antrian->kodedokter ? $antrian->kodedokter : '')
                    ->where('rj_status', '!=', 'F')
                    ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $antrian->tanggalperiksa ? $antrian->tanggalperiksa : '')
                    ->get();

                // cek Quota tersedia
                $kuota = isset($cekQuota->kuota)
                    ? (($cekQuota->kuota)
                        ? $cekQuota->kuota
                        : 0)
                    : 0;

                if (!$kuota) {
                    return $this->sendError($request, "Pendaftaran ke Poli ini tidak tersedia",  201);
                }

                if ($cekQuota->kuota - $cekDaftar->count() == 0) {
                    return $this->sendError($request, "Quota tidak tersedia",  201);
                }


                try {

                    // update mobile JKN
                    DB::table('referensi_mobilejkn_bpjs')
                        ->where('nobooking', $request->kodebooking)
                        ->update([
                            'status' => 'Checkin',
                            'validasi' => Carbon::now()->format('Y-m-d H:i:s')
                        ]);

                    // insert to rjhdr

                    // rjNo
                    $sql = "select nvl(max(rj_no)+1,1) rjno_max from rstxn_rjhdrs";
                    $rjNo = DB::scalar($sql);

                    // noAntrian
                    $sql = "select count(*) no_antrian
             from rstxn_rjhdrs
             where dr_id=:drId
             and to_char(rj_date,'ddmmyyyy')=:tgl
             and klaim_id!='KR'";

                    $noUrutAntrian = DB::scalar($sql, [
                        "drId" => $cekQuota->dr_id,
                        "tgl" => Carbon::createFromFormat('Y-m-d H:i:s', $waktucheckin)->format('dmY')
                    ]);
                    $noAntrian = $noUrutAntrian + 1;

                    // insert rjhdr
                    DB::table('rstxn_rjhdrs')->insert([
                        'rj_no' => $rjNo,
                        'rj_date' => DB::raw("to_date('" . $waktucheckin . "','yyyy-mm-dd hh24:mi:ss')"),
                        'reg_no' => DB::raw("upper(" . $antrian->norm . ")"),
                        'nobooking' => $request->kodebooking,
                        'no_antrian' => $noAntrian,

                        'klaim_id' => 'JM',
                        'poli_id' => $cekQuota->poli_id,
                        'dr_id' => $cekQuota->dr_id,
                        'shift' => $cekQuota->shift,

                        'txn_status' => 'A',
                        'rj_status' => 'A',
                        'erm_status' => 'A',

                        'pass_status' => 'O', //Baru lama

                        'cek_lab' => '0',
                        'sl_codefrom' => '02',
                        'kunjungan_internal_status' => '0',
                        // 'push_antrian_bpjs_status' => null, //status push antrian 200 /201/ 400
                        // 'push_antrian_bpjs_json' => null,  // response json
                        // 'datadaftarpolirj_json' => json_encode($this->dataDaftarPoliRJ, true),
                        // 'datadaftarpolirj_xml' => ArrayToXml::convert($this->dataDaftarPoliRJ),

                        'waktu_masuk_pelayanan' => DB::raw("to_date('" . $waktucheckin . "','yyyy-mm-dd hh24:mi:ss')"), //waktu masuk = rjdate

                        // 'vno_sep' => null,

                    ]);

                    $myAntreanadd = [
                        "kodebooking" => $request->kodebooking,
                        "jenispasien" => 'JKN', //Layanan UMUM BPJS
                        "nomorkartu" => $antrian->nomorkartu,
                        "nik" =>  $antrian->nik,
                        "nohp" =>  $antrian->nohp,
                        "kodepoli" => $antrian->kodepoli, //if null poliidRS
                        "namapoli" => $cekQuota->poli_desc,
                        "pasienbaru" => 0,
                        "norm" => $antrian->kodepoli,
                        "tanggalperiksa" => $antrian->tanggalperiksa,
                        "kodedokter" => $antrian->kodedokter, //if Null dridRS
                        "namadokter" => $cekQuota->namadokter,
                        "jampraktek" => $jammulai,
                        "jeniskunjungan" => $antrian->jeniskunjungan, //FKTP/FKTL/Kontrol/Internal
                        "nomorreferensi" => $antrian->nomorreferensi,
                        "nomorantrean" => $noAntrian,
                        "angkaantrean" => $noAntrian,
                        "estimasidilayani" => $antrian->estimasidilayani,
                        "sisakuotajkn" => $noAntrian->kuota - $noAntrian,
                        "kuotajkn" => $noAntrian->kuota,
                        "sisakuotanonjkn" => $noAntrian->kuota - $noAntrian,
                        "kuotanonjkn" => $noAntrian->kuota,
                        "keterangan" => "Peserta harap 1 jam lebih awal guna pencatatan administrasi.",
                    ];


                    $this->pushDataAntrian($myAntreanadd, $rjNo, $request->kodebooking, $request->waktu);

                    return $this->sendResponse($request, "OK", 200);
                } catch (Exception $e) {
                    return $this->sendError($request, $e->getMessage(), 201);
                }
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    public function batalantrean(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }
                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "kodebooking" => "required",
                    "keterangan" => "required"
                ]);

                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  201);
                }

                $antrian = DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $request->kodebooking)
                    ->first();

                if (!$antrian) {
                    return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.",  201);
                }

                if (!Carbon::parse($antrian->tanggalperiksa)->isToday()) {
                    return $this->sendError($request, "Tanggal periksa bukan hari ini.", 201);
                }

                if ($antrian->status == 'Batal') {
                    return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
                }

                if ($antrian->status == 'Checkin') {
                    return $this->sendError($request, "Pembatalan tidakbisa dilakukan, Anda Sudah Checkin pada " . $antrian->validasi, 201);
                }

                // update mobile JKN
                DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $request->kodebooking)
                    ->update([
                        'status' => 'Batal',
                        'keterangan_batal' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);



                return $this->sendResponse($request, "OK", 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }
    public function statusantrean(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }
                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "kodepoli" => "required",
                    "kodedokter" => "required",
                    "tanggalperiksa" => "required|date",
                    "jampraktek" => "required",
                ]);

                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  201);
                }

                if (Carbon::parse($request->tanggalperiksa)->endOfDay()->isPast()) {
                    return $this->sendError($request, "Tanggal periksa sudah terlewat", 201);
                }

                // cek dokter
                $kd_dr_bpjs = DB::table('rsmst_doctors')->where('kd_dr_bpjs',  $request->kodedokter ? $request->kodedokter : '')->get();

                if (!$kd_dr_bpjs->count()) {
                    return $this->sendError($request, "Dokter tidak ditemukan",  201);
                }

                // cek poli
                $kd_poli_bpjs = DB::table('rsmst_polis')->where('kd_poli_bpjs',  $request->kodepoli ? $request->kodepoli : '')->get();

                if (!$kd_poli_bpjs->count()) {
                    return $this->sendError($request, "Poli tidak ditemukan",  201);
                }




                $hari = strtoupper($this->hariIndo(Carbon::parse($request->tanggalperiksa)->dayName));
                $cekQuota = DB::table('scview_scpolis')
                    ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
                    ->where('kd_poli_bpjs', $request->kodepoli)
                    ->where('kd_dr_bpjs', $request->kodedokter)
                    ->where('day_desc', $hari)
                    // ->where('mulai_praktek', $jammulai . ':00')
                    // ->where('selesai_praktek', $jamselesai . ':00')
                    ->first();

                $cekDaftar = DB::table('rsview_rjkasir')
                    ->select('rj_no')
                    ->where('kd_poli_bpjs', $request->kodepoli ? $request->kodepoli : '')
                    ->where('kd_dr_bpjs', $request->kodedokter ? $request->kodedokter : '')
                    ->where('rj_status', '!=', 'F')
                    ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $request->tanggalperiksa ? $request->tanggalperiksa : '')
                    ->get();

                // cek Quota tersedia
                $kuota = isset($cekQuota->kuota)
                    ? (($cekQuota->kuota)
                        ? $cekQuota->kuota
                        : 0)
                    : 0;

                if (!$kuota) {
                    return $this->sendError($request, "Poli ini tidak tersedia",  201);
                }

                if ($cekQuota->kuota - $cekDaftar->count() == 0) {
                    return $this->sendError($request, "Quota tidak tersedia",  201);
                }

                // Pasien Dilayani
                $queryPasienDilayani = DB::table('rsview_rjkasir')
                    ->select(
                        DB::raw("to_char(rj_date,'dd/mm/yyyy hh24:mi:ss') AS rj_date"),
                        DB::raw("to_char(rj_date,'yyyymmddhh24miss') AS rj_date1"),
                        'rj_no',
                        'reg_no',
                        'reg_name',
                        'sex',
                        'address',
                        'thn',
                        'poli_desc',
                        'dr_name',
                        'no_antrian',
                        'waktu_masuk_poli',
                        'waktu_masuk_apt'
                    )
                    ->where('rj_status', '=', 'A')
                    // ->where('shift', '=', $this->shiftRjRef['shiftId'])
                    ->where('klaim_id', '!=', 'KR')
                    ->where(DB::raw("to_char(rj_date,'yyyy-mm-dd')"),  $request->tanggalperiksa ? $request->tanggalperiksa : '')
                    ->where('kd_dr_bpjs', '=', $request->kodedokter ? $request->kodedokter : '')
                    ->whereNotNull('waktu_masuk_poli') //not null
                    ->whereNull('waktu_masuk_apt') //null Status pelayanan
                    ->orderBy('no_antrian',  'asc')
                    ->orderBy('rj_date1',  'desc')
                    ->first();


                $noAntrian = isset($queryPasienDilayani->no_antrian) ?
                    (($queryPasienDilayani->no_antrian)
                        ? $queryPasienDilayani->no_antrian
                        : 0)
                    : 0;
                $waktuMasukPoli = isset($queryPasienDilayani->waktu_masuk_poli) ?
                    (($queryPasienDilayani->waktu_masuk_poli)
                        ? $queryPasienDilayani->waktu_masuk_poli
                        : 0)
                    : 0;
                $response = [
                    "namapoli" => $cekQuota->dr_name,
                    "namadokter" => $cekQuota->poli_desc,
                    "totalantrean" => $cekDaftar->count(),
                    "sisaantrean" => $cekDaftar->count() - $noAntrian,
                    "antreanpanggil" => $waktuMasukPoli,
                    "sisakuotajkn" => $cekQuota->kuota -  $noAntrian,
                    "kuotajkn" => $cekQuota->kuota,
                    "sisakuotanonjkn" => $cekQuota->kuota -  $noAntrian,
                    "kuotanonjkn" =>  $cekQuota->kuota,
                    "keterangan" => "Informasi antrian poliklinik " . Carbon::now()->format('Y-m-d H:i:s'),
                ];

                return $this->sendResponse($request, $response, 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    public function sisaantrean(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }
                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "kodebooking" => "required",
                ]);

                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  201);
                }

                $antrian = DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $request->kodebooking)
                    ->first();

                if (!$antrian) {
                    return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.",  201);
                }

                if ($antrian->status == 'Batal') {
                    return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
                }

                if ($antrian->status != 'Checkin') {
                    return $this->sendError($request, "Status Belum Checkin " . $request->kodebooking, 201);
                }

                // Pasien Dilayani
                $queryPasienRJ = DB::table('rsview_rjkasir')
                    ->select(
                        DB::raw("to_char(rj_date,'dd/mm/yyyy hh24:mi:ss') AS rj_date"),
                        DB::raw("to_char(rj_date,'yyyymmddhh24miss') AS rj_date1"),
                        'rj_no',
                        'reg_no',
                        'reg_name',
                        'sex',
                        'address',
                        'thn',
                        'poli_desc',
                        'dr_name',
                        'no_antrian',
                        'waktu_masuk_poli',
                        'waktu_masuk_apt',
                        'kd_poli_bpjs',
                        'kd_dr_bpjs'
                    )
                    ->where('nobooking', '=',  $request->kodedokter ? $request->kodedokter : '')
                    ->first();




                $noAntrian = isset($queryPasienRJ->no_antrian) ?
                    (($queryPasienRJ->no_antrian)
                        ? $queryPasienRJ->no_antrian
                        : 0)
                    : 0;

                if (!$noAntrian) {
                    return $this->sendError($request, "Data pasien tidak diteukan " . $request->kodebooking, 201);
                }

                $waktuMasukPoli = isset($queryPasienRJ->waktu_masuk_poli) ?
                    (($queryPasienRJ->waktu_masuk_poli)
                        ? $queryPasienRJ->waktu_masuk_poli
                        : 0)
                    : 0;

                $poliDesc = isset($queryPasienRJ->poli_desc) ?
                    (($queryPasienRJ->poli_desc)
                        ? $queryPasienRJ->poli_desc
                        : 0)
                    : 0;

                $drName = isset($queryPasienRJ->dr_name) ?
                    (($queryPasienRJ->dr_name)
                        ? $queryPasienRJ->dr_name
                        : 0)
                    : 0;

                $kd_poli_bpjs = isset($queryPasienRJ->kd_poli_bpjs) ?
                    (($queryPasienRJ->kd_poli_bpjs)
                        ? $queryPasienRJ->kd_poli_bpjs
                        : 0)
                    : 0;

                $kd_dr_bpjs = isset($queryPasienRJ->kd_dr_bpjs) ?
                    (($queryPasienRJ->kd_dr_bpjs)
                        ? $queryPasienRJ->kd_dr_bpjs
                        : 0)
                    : 0;

                $hari = strtoupper($this->hariIndo(Carbon::now()->dayName));
                $cekDaftar = DB::table('scview_scpolis')
                    ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
                    ->where('kd_poli_bpjs', $kd_poli_bpjs)
                    ->where('kd_dr_bpjs', $kd_dr_bpjs)
                    ->where('day_desc', $hari)
                    // ->where('mulai_praktek', $jammulai . ':00')
                    // ->where('selesai_praktek', $jamselesai . ':00')
                    ->first();

                $kuota = isset($cekDaftar->kuota) ?
                    (($cekDaftar->kuota)
                        ? $cekDaftar->kuota
                        : 0)
                    : 0;


                $response = [
                    "nomorantrean" => $noAntrian,
                    "namapoli" => $poliDesc,
                    "namadokter" => $drName,
                    "sisaantrean" => $kuota -  $noAntrian,
                    "antreanpanggil" => $waktuMasukPoli,
                    "waktutunggu" => $kuota -  $noAntrian,
                    "keterangan" => "Informasi antrian poliklinik " . Carbon::now()->format('Y-m-d H:i:s'),

                ];

                return $this->sendResponse($request, $response, 200);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    public function pasienbaru(Request $request)
    {

        $credentials = [
            'name' => $request->header('x-username'),
            'token' => $request->header('x-token')

        ];

        $username = isset(User::where('name', $credentials['name'])->first()->name) ? User::where('name', $credentials['name'])->first()->name : '';
        // jika user name ditemukan
        if ($username == $credentials['name']) {
            // jika token name ditemukan
            if ((!empty($credentials['token']))
                && (User::where('name', $credentials['name'])->first()->name == $credentials['name'])
            ) {
                // cek token
                if ($this->cektoken($credentials['token']) != 1) {
                    return ($this->cektoken($credentials['token']));
                }
                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "kodebooking" => "required",
                ]);

                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($request, $validator->errors()->first(),  400);
                }

                return $this->sendError($request, "Anda belum memiliki No RM di RSI Madinah (Pasien Baru). Silahkan daftar secara offline.", 400);
            }
        }
        return $this->sendError($request, "Unauthorized ", 401);
    }

    /////////////////////////////
    // Push ke BPJS Antrol task ID
    /////////////////////////////

    private function pushDataAntrian($myAntreanadd, $rjNo, $kodebooking, $waktu)
    {
        $antreanadd = $myAntreanadd;
        // Tambah Antrean

        $cekAntrianAntreanBPJS = DB::table('rstxn_rjhdrs')
            ->select('push_antrian_bpjs_status', 'push_antrian_bpjs_json')
            ->where('rj_no', $rjNo)
            ->first();

        $cekAntrianAntreanBPJSStatus = isset($cekAntrianAntreanBPJS->push_antrian_bpjs_status) ? $cekAntrianAntreanBPJS->push_antrian_bpjs_status : "";
        // 1 cek proses pada database status 208 task id sudah terbit
        if ($cekAntrianAntreanBPJSStatus == 200 || $cekAntrianAntreanBPJSStatus == 208) {
        } else {
            // Tambah Antrean
            // $HttpGetBpjs =  AntrianTrait::tambah_antrean($antreanadd)->getOriginalContent();
            // set http response to public
            // $HttpGetBpjsStatus = $HttpGetBpjs['metadata']['code']; //status 200 201 400 ..
            // $HttpGetBpjsJson = json_encode($HttpGetBpjs, true); //Return Response Tambah Antrean

            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'push_antrian_bpjs_status' => 200,
                    'push_antrian_bpjs_json' => '{}'
                ]);
        }

        /////////////////////////
        // Update TaskId 3
        /////////////////////////
        $this->pushDataTaskId($kodebooking, 3, $waktu);
    }

    private function pushDataTaskId($noBooking, $taskId, $waktu): void
    {
        $this->update_antrean($noBooking, $taskId, $waktu, "")->getOriginalContent();
    }




    public function x(Request $request)
    {
        $noAntrian = 10;
        $jammulai = '11:00';
        $tanggalperiksa = $request->tanggalperiksa . ' ' . $jammulai . ':00';
        $jadwalEstimasiTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksa)->addMinutes(10 * ($noAntrian + 1))->timestamp * 1000;

        $date = Carbon::createFromTimestamp($jadwalEstimasiTimestamp / 1000)->toDateTimeString();
        return $tanggalperiksa . '  ' . $date . '  ' . $jadwalEstimasiTimestamp;
    }
}


    /////////////
    // API SIMRS
    /////////////
