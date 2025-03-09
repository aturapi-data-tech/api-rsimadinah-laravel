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


    /**
     * Metode autentikasi terpusat.
     *
     * @param Request $request
     * @return mixed (User atau JsonResponse error)
     */
    protected function authenticate(Request $request)
    {
        $username = $request->header('x-username');
        $token    = $request->header('x-token');

        if (!$username || !$token) {
            return $this->sendError($request, "Unauthorized: Missing credentials", 201);
        }

        $user = User::where('name', $username)->first();
        if (!$user) {
            return $this->sendError($request, "Unauthorized: User not found", 201);
        }

        if ($this->cektoken($token) !== 1) {
            return $this->cektoken($token);
        }

        return $user;
    }

    /**
     * Endpoint untuk mendapatkan token.
     */
    public function token(Request $request)
    {
        $credentials = [
            'name'     => $request->header('x-username'),
            'password' => $request->header('x-password')
        ];

        if (Auth::attempt($credentials)) {
            $token = $this->createToken($credentials['name'], $credentials['password']);
            return $this->sendResponse($request, ['token' => $token], 200);
        } else {
            return $this->sendError($request, "Unauthorized (Username dan Password Salah)", 201);
        }
    }

    /**
     * Endpoint jadwal operasi RS.
     */
    public function jadwaloperasirs(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make(
            $request->all(),
            [
                "tanggalawal"  => "required|date|date_format:Y-m-d",
                "tanggalakhir" => "required|date|date_format:Y-m-d|after:tanggalawal",
            ],
            [
                'tanggalawal.required' => 'Tanggal awal wajib diisi.',
                'tanggalawal.date' => 'Tanggal awal harus berupa tanggal yang valid.',
                'tanggalawal.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',

                'tanggalakhir.required' => 'Tanggal akhir wajib diisi.',
                'tanggalakhir.date' => 'Tanggal akhir harus berupa tanggal yang valid.',
                'tanggalakhir.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
                'tanggalakhir.after' => 'Tanggal akhir harus setelah tanggal awal.',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $startDate = Carbon::parse($request->tanggalawal)->startOfDay();
        $endDate   = Carbon::parse($request->tanggalakhir)->endOfDay();

        $jadwalops = DB::table('booking_operasi')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        if ($jadwalops->isEmpty()) {
            return $this->sendError($request, 'Data Tidak ditemukan', 201);
        }

        $jadwals = [];
        foreach ($jadwalops as $jadwalop) {
            $jadwals[] = [
                "kodebooking"    => $jadwalop->no_rawat,
                "tanggaloperasi" => Carbon::parse($jadwalop->tanggal)->format('Y-m-d'),
                "jenistindakan"  => $jadwalop->nm_paket,
                "kodepoli"       => $jadwalop->POLI_ID ?? 'BED',
                "namapoli"       => 'BEDAH',
                "terlaksana"     => $jadwalop->status === 'Menunggu' ? 0 : 1,
                "nopeserta"      => $jadwalop->no_peserta,
                "lastupdate"     => now(config('app.timezone'))->timestamp * 1000,
            ];
        }

        return $this->sendResponse($request, ["list" => $jadwals], 200);
    }

    /**
     * Endpoint jadwal operasi pasien.
     */
    public function jadwaloperasipasien(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "nopeserta" => "required|digits:13",
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $jadwalops = DB::table('booking_operasi')
            ->where('no_peserta', $request->nopeserta)
            ->get();

        if ($jadwalops->isEmpty()) {
            return $this->sendError($request, 'Data Tidak ditemukan', 201);
        }

        $jadwals = [];
        foreach ($jadwalops as $jadwalop) {
            $jadwals[] = [
                "kodebooking"    => $jadwalop->no_rawat,
                "tanggaloperasi" => Carbon::parse($jadwalop->tanggal)->format('Y-m-d'),
                "jenistindakan"  => $jadwalop->nm_paket,
                "kodepoli"       => $jadwalop->POLI_ID ?? 'BED',
                "namapoli"       => 'BEDAH',
                "terlaksana"     => $jadwalop->status === 'Menunggu' ? 0 : 1,
                "nopeserta"      => $jadwalop->no_peserta,
                "lastupdate"     => now(config('app.timezone'))->timestamp * 1000,
            ];
        }

        return $this->sendResponse($request, ["list" => $jadwals], 200);
    }

    // JKN///////////////////////////////
    public function ambilantrean(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $rules = [
            "nomorkartu"     => "required|numeric|digits:13",
            "nik"            => "required|numeric|digits:16",
            "nohp"           => "required",
            "kodepoli"       => "required",
            "norm"           => "required",
            "tanggalperiksa" => "required|date|date_format:Y-m-d",
            "kodedokter"     => "required",
            "jampraktek"     => "required",
            "jeniskunjungan" => "required|numeric|between:1,4",
        ];
        if ($request->jeniskunjungan != 2) {
            $rules['nomorreferensi'] = "required";
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        // Cek pasien
        $pasien = DB::table('rsmst_pasiens')
            ->select('reg_no', 'nokartu_bpjs', 'nik_bpjs')
            ->where('nokartu_bpjs', $request->nomorkartu)
            ->first();

        if (!$pasien) {
            return $this->sendError($request, "Nomor Kartu BPJS Pasien termasuk Pasien Baru di RSI Madinah. Silahkan daftar melalui pendaftaran offline", 201);
        }

        if ($pasien->nik_bpjs != $request->nik) {
            return $this->sendError($request, "NIK anda yang terdaftar di BPJS dengan Di RSI Madinah berbeda. Silahkan perbaiki melalui pendaftaran offline", 201);
        }

        // Pastikan norm sama dengan reg_no pasien
        if ($request->filled('norm')) {
            if ($request->norm != $pasien->reg_no) {
                $request->merge(['norm' => $pasien->reg_no]);
            }
        } else {
            if (!empty($pasien->reg_no)) {
                $request->merge(['norm' => $pasien->reg_no]);
            } else {
                return $this->sendError($request, "Nomor Rekam medis tidak ditemukan, silakan konfirmasi petugas untuk melakukan update data anda.", 201);
            }
        }

        // Validasi tanggal periksa
        if (Carbon::parse($request->tanggalperiksa)->endOfDay()->isPast()) {
            return $this->sendError($request, "Tanggal periksa sudah terlewat", 201);
        }
        if (Carbon::parse($request->tanggalperiksa) > Carbon::now(config('app.timezone'))->addDay(35)) {
            return $this->sendError($request, "Antrian hanya dapat dibuat untuk 35 hari ke kedepan", 201);
        }

        // Cek duplikasi antrian
        $antrian_nik = DB::table('referensi_mobilejkn_bpjs')
            ->where('tanggalperiksa', $request->tanggalperiksa)
            ->where('nik', $request->nik)
            ->where('status', '!=', 'Batal')
            ->first();
        if ($antrian_nik) {
            return $this->sendError($request, "Terdapat Antrian (" . $antrian_nik->nobooking . ") dengan nomor NIK yang sama pada tanggal tersebut yang belum selesai. Silahkan batalkan terlebih dahulu jika ingin mendaftarkan lagi.", 201);
        }

        // Cek dokter dan poli
        $doctor = DB::table('rsmst_doctors')
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->first();
        if (!$doctor) {
            return $this->sendError($request, "Dokter tidak ditemukan", 201);
        }

        $poli = DB::table('rsmst_polis')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->first();
        if (!$poli) {
            return $this->sendError($request, "Poli tidak ditemukan", 201);
        }

        $hari       = strtoupper($this->hariIndo(Carbon::parse($request->tanggalperiksa)->dayName));
        $jammulai   = substr($request->jampraktek, 0, 5);
        $jamselesai = substr($request->jampraktek, 6, 5);

        // Cek quota dan jadwal dokter-poli
        $cekQuota = DB::table('scview_scpolis')
            ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'dr_id', 'poli_desc', 'dr_name')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->where('day_desc', $hari)
            ->where('mulai_praktek', $jammulai . ':00')
            ->where('selesai_praktek', $jamselesai . ':00')
            ->first();
        if (!$cekQuota || !$cekQuota->kuota) {
            return $this->sendError($request, "Pendaftaran ke Poli " . $poli->poli_desc . " tanggal " . $request->tanggalperiksa . " tidak tersedia", 201);
        }

        $cekDaftar = DB::table('rsview_rjkasir')
            ->select('rj_no')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->where('rj_status', '!=', 'F')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $request->tanggalperiksa)
            ->get();
        if (($cekQuota->kuota - $cekDaftar->count()) <= 0) {
            return $this->sendError($request, "Quota Poli " . $poli->poli_desc . " Dokter " . $doctor->dr_name . " tanggal " . $request->tanggalperiksa . " tidak tersedia", 201);
        }

        // Hitung nomor antrian
        $sql = "select count(*) no_antrian
                from rstxn_rjhdrs
                where dr_id = :drId
                and to_char(rj_date,'ddmmyyyy') = :tgl
                and klaim_id != 'KR'";
        $noUrutAntrian = DB::scalar($sql, [
            "drId" => $cekQuota->dr_id,
            "tgl"  => Carbon::createFromFormat('Y-m-d', $request->tanggalperiksa, config('app.timezone'))->format('dmY')
        ]);
        $sqlBooking = "select count(*) no_antrian
                       from referensi_mobilejkn_bpjs
                       where kodedokter = :drId
                       and tanggalperiksa = :tgl";
        $noUrutAntrianBooking = DB::scalar($sqlBooking, [
            "drId" => $request->kodedokter,
            "tgl"  => $request->tanggalperiksa
        ]);
        $noAntrian = $noUrutAntrian + $noUrutAntrianBooking + 1;

        $tanggalperiksaFull = $request->tanggalperiksa . ' ' . $jammulai . ':00';
        $jadwalEstimasiTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksaFull, config('app.timezone'))
            ->addMinutes(10 * ($noAntrian + 1))
            ->timestamp * 1000;

        $noBooking = Carbon::now(config('app.timezone'))->format('YmdHis') . 'JKN';

        try {
            DB::table('referensi_mobilejkn_bpjs')->insert([
                "nobooking"         => $noBooking,
                "no_rawat"          => $noBooking,
                "nomorkartu"        => $request->nomorkartu,
                "nik"               => $request->nik,
                "nohp"              => $request->nohp,
                "kodepoli"          => $request->kodepoli,
                "pasienbaru"        => 0,
                "norm"              => strtoupper($request->norm),
                "tanggalperiksa"    => $request->tanggalperiksa,
                "kodedokter"        => $request->kodedokter,
                "jampraktek"        => $request->jampraktek,
                "jeniskunjungan"    => $request->jeniskunjungan,
                "nomorreferensi"    => $request->nomorreferensi ?? null,
                "nomorantrean"      => $request->kodepoli . '-' . $noAntrian,
                "angkaantrean"      => $noAntrian,
                "estimasidilayani"  => $jadwalEstimasiTimestamp,
                "sisakuotajkn"      => $cekQuota->kuota - $cekDaftar->count(),
                "kuotajkn"          => $cekQuota->kuota,
                "sisakuotanonjkn"   => $cekQuota->kuota - $cekDaftar->count(),
                "kuotanonjkn"       => $cekQuota->kuota,
                "status"            => "Belum",
                "validasi"          => "",
                "statuskirim"       => "Belum",
                "keterangan_batal"  => "",
                "tanggalbooking"    => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
                "daftardariapp"     => "JKNMobileAPP",
            ]);

            $response = [
                "nomorantrean"     => $request->kodepoli . '-' . $noAntrian,
                "angkaantrean"     => $noAntrian,
                "kodebooking"      => $noBooking,
                "norm"             => $request->norm,
                "namapoli"         => $cekQuota->poli_desc,
                "namadokter"       => $cekQuota->dr_name,
                "estimasidilayani" => $jadwalEstimasiTimestamp,
                "sisakuotajkn"     => $cekQuota->kuota - $cekDaftar->count(),
                "kuotajkn"         => $cekQuota->kuota,
                "sisakuotanonjkn"  => $cekQuota->kuota - $cekDaftar->count(),
                "kuotanonjkn"      => $cekQuota->kuota,
                "keterangan"       => 'Peserta harap 60 menit lebih awal guna pencatatan administrasi',
            ];

            return $this->sendResponse($request, $response, 200);
        } catch (Exception $e) {
            return $this->sendError($request, $e->getMessage(), 500);
        }
    }


    /**
     * Endpoint checkin antrean.
     */
    public function checkinantrean(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "kodebooking" => "required",
            "waktu"       => "required",
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $antrian = DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $request->kodebooking)
            ->first();
        if (!$antrian) {
            return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.", 201);
        }
        if (!Carbon::parse($antrian->tanggalperiksa)->isToday()) {
            return $this->sendError($request, "Tanggal periksa bukan hari ini, tetapi tgl " . $antrian->tanggalperiksa, 201);
        }
        if ($antrian->status == 'Batal') {
            return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
        }
        if ($antrian->status == 'Checkin') {
            return $this->sendError($request, "Anda Sudah Checkin pada " . $antrian->validasi, 201);
        }

        $jammulai = substr($antrian->jampraktek, 0, 5);
        $tanggalperiksaFull = $antrian->tanggalperiksa . ' ' . $jammulai . ':00';
        $waktucheckin = Carbon::createFromTimestamp($request->waktu / 1000)
            ->timezone(config('app.timezone'))
            ->toDateTimeString();

        $checkIn2Jam = Carbon::createFromFormat('Y-m-d H:i:s', $waktucheckin, config('app.timezone'))
            ->diffInHours(Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksaFull, config('app.timezone')), false);
        if ($checkIn2Jam < -2) {
            return $this->sendError($request, "Lakukan Checkin 2 Jam Sebelum Pelayanan, Pelayanan dimulai " . $tanggalperiksaFull, 201);
        }
        if ($checkIn2Jam > 2) {
            return $this->sendError($request, "Checkin Anda sudah expired " . abs($checkIn2Jam) . " Jam yang lalu, Silahkan konfirmasi ke loket pendaftaran ", 201);
        }

        $hari = strtoupper($this->hariIndo(Carbon::parse($tanggalperiksaFull)->dayName));
        $cekQuota = DB::table('scview_scpolis')
            ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
            ->where('kd_poli_bpjs', $antrian->kodepoli)
            ->where('kd_dr_bpjs', $antrian->kodedokter)
            ->where('day_desc', $hari)
            ->where('mulai_praktek', $jammulai . ':00')
            ->where('selesai_praktek', substr($antrian->jampraktek, 6, 5) . ':00')
            ->first();
        if (!$cekQuota) {
            return $this->sendError($request, "Ada perubahan jadwal pelayanan, jadwal Dokter di Poli tersebut tidak ditemukan.", 201);
        }

        $cekDaftar = DB::table('rsview_rjkasir')
            ->select('rj_no')
            ->where('kd_poli_bpjs', $antrian->kodepoli)
            ->where('kd_dr_bpjs', $antrian->kodedokter)
            ->where('rj_status', '!=', 'F')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $antrian->tanggalperiksa)
            ->get();
        if (($cekQuota->kuota - $cekDaftar->count()) <= 0) {
            return $this->sendError($request, "Quota Pelayanan Poli " . $cekQuota->poli_desc . " Dokter " . $cekQuota->dr_name . " pada hari " . $hari . " Penuh.", 201);
        }

        try {
            $sql = "select nvl(max(rj_no)+1,1) rjno_max from rstxn_rjhdrs";
            $rjNo = DB::scalar($sql);
            $sqlAntrian = "select count(*) no_antrian
                           from rstxn_rjhdrs
                           where dr_id = :drId
                           and to_char(rj_date,'ddmmyyyy') = :tgl
                           and klaim_id != 'KR'";
            $noUrutAntrian = DB::scalar($sqlAntrian, [
                "drId" => $cekQuota->dr_id,
                "tgl"  => Carbon::createFromFormat('Y-m-d H:i:s', $waktucheckin, config('app.timezone'))->format('dmY')
            ]);
            $noAntrian = $noUrutAntrian + 1;

            DB::table('rstxn_rjhdrs')->insert([
                'rj_no'                => $rjNo,
                'rj_date'              => DB::raw("to_date('" . $waktucheckin . "', 'yyyy-mm-dd hh24:mi:ss')"),
                'reg_no'               => strtoupper($antrian->norm),
                'nobooking'            => $request->kodebooking,
                'no_antrian'           => $noAntrian,
                'klaim_id'             => 'JM',
                'poli_id'              => $cekQuota->poli_id,
                'dr_id'                => $cekQuota->dr_id,
                'shift'                => $cekQuota->shift,
                'txn_status'           => 'A',
                'rj_status'            => 'A',
                'erm_status'           => 'A',
                'pass_status'          => 'O',
                'cek_lab'              => '0',
                'sl_codefrom'          => '02',
                'kunjungan_internal_status' => '0',
                'waktu_masuk_pelayanan' => DB::raw("to_date('" . $waktucheckin . "', 'yyyy-mm-dd hh24:mi:ss')")
            ]);
        } catch (Exception $e) {
            return $this->sendError($request, $e->getMessage(), 500);
        }

        try {
            DB::table('referensi_mobilejkn_bpjs')
                ->where('nobooking', $request->kodebooking)
                ->update([
                    'status'   => 'Checkin',
                    'validasi' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s')
                ]);

            // Siapkan data untuk push ke antrean BPJS
            $myAntreanadd = [
                "kodebooking"   => $request->kodebooking,
                "jenispasien"   => 'JKN',
                "nomorkartu"    => $antrian->nomorkartu,
                "nik"           => $antrian->nik,
                "nohp"          => $antrian->nohp,
                "kodepoli"      => $antrian->kodepoli,
                "namapoli"      => $cekQuota->poli_desc,
                "pasienbaru"    => 0,
                "norm"          => $antrian->kodepoli,
                "tanggalperiksa" => $antrian->tanggalperiksa,
                "kodedokter"    => $antrian->kodedokter,
                "namadokter"    => $cekQuota->dr_name,
                "jampraktek"    => $jammulai,
                "jeniskunjungan" => $antrian->jeniskunjungan,
                "nomorreferensi" => $antrian->nomorreferensi,
                "nomorantrean"  => $noAntrian,
                "angkaantrean"  => $noAntrian,
                "estimasidilayani" => $antrian->estimasidilayani,
                "sisakuotajkn"     => $cekQuota->kuota - $noAntrian,
                "kuotajkn"         => $cekQuota->kuota,
                "sisakuotanonjkn"  => $cekQuota->kuota - $noAntrian,
                "kuotanonjkn"      => $cekQuota->kuota,
                "keterangan"       => "Peserta harap 1 jam lebih awal guna pencatatan administrasi.",
            ];

            $this->pushDataAntrian($myAntreanadd, $rjNo, $request->kodebooking, $request->waktu);
            return $this->sendResponse($request, "OK Peserta harap 1 jam lebih awal guna pencatatan administrasi " . $request->kodebooking, 200);
        } catch (Exception $e) {
            return $this->sendError($request, $e->getMessage(), 500);
        }
    }

    /**
     * Endpoint batal antrean.
     */
    public function batalantrean(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "kodebooking" => "required",
            "keterangan"  => "required"
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $antrian = DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $request->kodebooking)
            ->first();
        if (!$antrian) {
            return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.", 201);
        }
        if ($antrian->status == 'Batal') {
            return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
        }
        if ($antrian->status == 'Checkin') {
            return $this->sendError($request, "Pembatalan tidak bisa dilakukan, Anda Sudah Checkin pada " . $antrian->validasi, 201);
        }

        DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $request->kodebooking)
            ->update([
                'status'             => 'Batal',
                'keterangan_batal'   => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s')
            ]);

        return $this->sendResponse($request, "OK", 200);
    }



    /**
     * Endpoint status antrean.
     */
    public function statusantrean(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "kodepoli"       => "required",
            "kodedokter"     => "required",
            "tanggalperiksa" => "required|date",
            "jampraktek"     => "required",
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        if (Carbon::parse($request->tanggalperiksa)->endOfDay()->isPast()) {
            return $this->sendError($request, "Tanggal periksa sudah terlewat", 201);
        }

        $doctor = DB::table('rsmst_doctors')
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->get();
        if ($doctor->isEmpty()) {
            return $this->sendError($request, "Dokter tidak ditemukan", 201);
        }

        $poli = DB::table('rsmst_polis')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->get();
        if ($poli->isEmpty()) {
            return $this->sendError($request, "Poli tidak ditemukan", 201);
        }

        $hari = strtoupper($this->hariIndo(Carbon::parse($request->tanggalperiksa)->dayName));
        $cekQuota = DB::table('scview_scpolis')
            ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->where('day_desc', $hari)
            ->first();

        $cekDaftar = DB::table('rsview_rjkasir')
            ->select('rj_no')
            ->where('kd_poli_bpjs', $request->kodepoli)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->where('rj_status', '!=', 'F')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $request->tanggalperiksa)
            ->get();

        if (!$cekQuota || !$cekQuota->kuota || ($cekQuota->kuota - $cekDaftar->count()) == 0) {
            return $this->sendError($request, "Quota tidak tersedia", 201);
        }

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
            ->where('klaim_id', '!=', 'KR')
            ->where(DB::raw("to_char(rj_date,'yyyy-mm-dd')"), $request->tanggalperiksa)
            ->where('kd_dr_bpjs', $request->kodedokter)
            ->whereNotNull('waktu_masuk_poli')
            ->whereNull('waktu_masuk_apt')
            ->orderBy('no_antrian', 'asc')
            ->orderBy(DB::raw("to_char(rj_date,'yyyymmddhh24miss')"), 'desc')
            ->first();

        $noAntrian      = $queryPasienDilayani->no_antrian ?? 0;
        $waktuMasukPoli = $queryPasienDilayani->waktu_masuk_poli ?? null;

        $response = [
            "namapoli"         => $cekQuota->dr_name,
            "namadokter"       => $cekQuota->poli_desc,
            "totalantrean"     => $cekDaftar->count(),
            "sisaantrean"      => $cekDaftar->count() - $noAntrian,
            "antreanpanggil"   => $waktuMasukPoli,
            "sisakuotajkn"     => $cekQuota->kuota - $noAntrian,
            "kuotajkn"         => $cekQuota->kuota,
            "sisakuotanonjkn"  => $cekQuota->kuota - $noAntrian,
            "kuotanonjkn"      => $cekQuota->kuota,
            "keterangan"       => "Informasi antrian poliklinik " . Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];

        return $this->sendResponse($request, $response, 200);
    }


    /**
     * Endpoint sisa antrean.
     */
    public function sisaantrean(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "kodebooking" => "required",
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        $antrian = DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $request->kodebooking)
            ->first();
        if (!$antrian) {
            return $this->sendError($request, "No Booking (" . $request->kodebooking . ") invalid.", 201);
        }
        if ($antrian->status == 'Batal') {
            return $this->sendError($request, "Antrian telah dibatalkan sebelumnya.", 201);
        }
        if ($antrian->status != 'Checkin') {
            return $this->sendError($request, "Status Belum Checkin " . $request->kodebooking, 201);
        }

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
            ->where('nobooking', $request->kodebooking)
            ->first();

        $noAntrian = $queryPasienRJ->no_antrian ?? 0;
        if (!$noAntrian) {
            return $this->sendError($request, "Data pasien tidak ditemukan " . $request->kodebooking, 201);
        }

        $waktuMasukPoli = $queryPasienRJ->waktu_masuk_poli ?? null;
        $response = [
            "nomorantrean" => $noAntrian,
            "namapoli"     => $queryPasienRJ->poli_desc ?? '',
            "namadokter"   => $queryPasienRJ->dr_name ?? '',
            "sisaantrean"  => ($queryPasienRJ->kuota ?? 0) - $noAntrian,
            "antreanpanggil" => $waktuMasukPoli,
            "waktutunggu"   => ($queryPasienRJ->kuota ?? 0) - $noAntrian,
            "keterangan"   => "Informasi antrian poliklinik " . Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];

        return $this->sendResponse($request, $response, 200);
    }

    /**
     * Endpoint untuk pasien baru.
     */
    public function pasienbaru(Request $request)
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            "kodebooking" => "required",
        ]);
        if ($validator->fails()) {
            return $this->sendError($request, $validator->errors()->first(), 201);
        }

        return $this->sendError($request, "Anda belum memiliki No RM di RSI Madinah (Pasien Baru). Silahkan daftar secara offline.", 201);
    }

    /////////////////////////////
    // Push ke BPJS Antrol task ID
    /////////////////////////////

    /**
     * Metode private untuk push data antrean ke BPJS.
     */
    private function pushDataAntrian($myAntreanadd, $rjNo, $kodebooking, $waktu)
    {
        $cekAntrianBPJS = DB::table('rstxn_rjhdrs')
            ->select('push_antrian_bpjs_status', 'push_antrian_bpjs_json')
            ->where('rj_no', $rjNo)
            ->first();

        $statusBPJS = $cekAntrianBPJS->push_antrian_bpjs_status ?? "";
        if ($statusBPJS == 200 || $statusBPJS == 208) {
            // Data sudah diproses
        } else {
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'push_antrian_bpjs_status' => 200,
                    'push_antrian_bpjs_json'   => '{}'
                ]);
        }

        $this->pushDataTaskId($kodebooking, 3, $waktu);
    }

    /**
     * Metode private untuk update task ID.
     */
    private function pushDataTaskId($noBooking, $taskId, $waktu): void
    {
        $this->update_antrean($noBooking, $taskId, $waktu, "")->getOriginalContent();
    }

    /**
     * Metode debugging (tidak untuk produksi).
     */
    public function x(Request $request)
    {
        return $this->sendError($request, 'yyyy', 201);

        $waktucheckin = '2023-11-24 10:30:00';
        $tanggalperiksa = '2023-11-23 15:15:00';

        $hoursDifference = Carbon::createFromFormat('Y-m-d H:i:s', $waktucheckin, 'Asia/Jakarta')->diffInHours(Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksa, 'Asia/Jakarta'), false);

        echo "Difference in hours: " . $hoursDifference;
        dd('xxxx');

        // $noAntrian = 10;
        // $jammulai = '11:00';
        // $tanggalperiksa = $request->tanggalperiksa . ' ' . $jammulai . ':00';
        // $jadwalEstimasiTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksa, 'Asia/Jakarta')->addMinutes(10 * ($noAntrian + 1))->timestamp * 1000;

        // $date = Carbon::createFromTimestamp($jadwalEstimasiTimestamp / 1000)->toDateTimeString();
        // return $tanggalperiksa . '  ' . $date . '  ' . $jadwalEstimasiTimestamp;
    }
}


    /////////////
    // API SIMRS
    /////////////
