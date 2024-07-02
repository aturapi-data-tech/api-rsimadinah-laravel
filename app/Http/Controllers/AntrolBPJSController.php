<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

use App\Http\Traits\TraitJWTRsiMadinah;

use App\Models\User;

class AntrolBPJSController extends Controller
{
    use TraitJWTRsiMadinah;
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
            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError("Unauthorized (Username dan Password Salah)", 401);
        }
    }


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
                && ($this->cektoken($credentials['token']))
            ) {

                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "tanggalawal" => "required|date|date_format:Y-m-d",
                    "tanggalakhir" => "required|date|date_format:Y-m-d|after:tanggalawal",
                ]);
                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($validator->errors()->first(),  201);
                }

                $request['tanggalawal'] = Carbon::parse($request->tanggalawal)->startOfDay();
                $request['tanggalakhir'] = Carbon::parse($request->tanggalakhir)->endOfDay();

                $jadwalops = DB::table('booking_operasi')
                    ->whereBetween('tanggal', [$request->tanggalawal, $request->tanggalakhir])
                    ->get();
                // if data kosong
                if (!$jadwalops->count()) {
                    return $this->sendError('Data Tidak ditemukan',);
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
                return $this->sendResponse($response, 200);
            }
        }
        return $this->sendError("Unauthorized ", 401);
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
                && ($this->cektoken($credentials['token']))
            ) {

                //proses data
                // Validator
                $validator = Validator::make($request->all(), [
                    "nopeserta" => "required|digits:13",
                ]);
                // if valoidator fails
                if ($validator->fails()) {
                    return $this->sendError($validator->errors()->first(),  201);
                }


                $jadwalops = DB::table('booking_operasi')
                    ->where('no_peserta', [$request->nopeserta])
                    ->get();

                // if data kosong
                if (!$jadwalops->count()) {
                    return $this->sendError('Data Tidak ditemukan',);
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
                return $this->sendResponse($response, 200);
            }
        }
        return $this->sendError("Unauthorized ", 401);
    }


    /////////////
    // API SIMRS
    /////////////

}
