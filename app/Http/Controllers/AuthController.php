<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    public function requestReset(Request $request)
    {
        $input = $request->only('identity');

        $user = User::where('email', $input['identity'])
            ->orWhere('no_hp', $input['identity'])
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Email atau nomor hp tidak ditemukan'], 404);
        }

        $otp = rand(100000, 999999);
        $user->password_reset_code = $otp;
        $user->save();

        Mail::send('emails.reset', ['otp' => $otp], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('Kode OTP Reset Password Anda');
        });

        return response()->json(['message' => 'Kode OTP telah dikirim ke email anda, silahkan cek juga folder spam jika email tidak masuk', 'email' => $user->email], 200);
    }

    public function verifyOtp(Request $request)
    {
        $input = $request->only('identity', 'otp');

        $user = User::where('email', $input['identity'])
            ->orWhere('no_hp', $input['identity'])
            ->first();

        if (!$user || $user->password_reset_code != $input['otp']) {
            return response()->json(['error' => 'Kode OTP tidak valid'], 400);
        }

        $token = Hash::make(Str::random(60));
        $user->password_reset_token = $token;
        $user->save();

        return response()->json(['token' => $token], 200);
    }

    public function resetPassword(Request $request)
    {
        $input = $request->only('token', 'password');

        $user = User::where('password_reset_token', $input['token'])->first();

        if (!$user) {
            return response()->json(['error' => 'Token tidak valid'], 400);
        }

        $user->password = Hash::make($input['password']);
        $user->password_reset_token = null;
        $user->password_reset_code = null;
        $user->save();

        return response()->json(['message' => 'Password berhasil diubah'], 200);
    }

    public function countByRegion()
    {
        $kecamatans = User::where('status', '!=', 'Admin')->select('kecamatan')
            ->distinct()
            ->get();

        $output = [];
        foreach ($kecamatans as $kecamatan) {
            $kelurahanCounts = User::where('kecamatan', $kecamatan->kecamatan)
                ->groupBy('kelurahan')
                ->selectRaw('kelurahan as Nama, COUNT(*) as Jumlah')
                ->get()
                ->toArray();

            $output['kecamatan'][] = [
                'Nama' => $kecamatan->kecamatan,
                'Jumlah' => User::where('kecamatan', $kecamatan->kecamatan)->count(),
                'Kelurahan' => $kelurahanCounts
            ];
        }

        return response()->json($output);
    }

    public function register(Request $request)
    {
        $messages = [
            'required' => ':attribute harus diisi.',
            'string' => ':attribute harus berupa teks.',
            'image' => ':attribute harus berupa gambar.',
            'mimes' => ':attribute harus berformat :values.',
            'max' => ':attribute tidak boleh lebih dari :max karakter.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'nik.unique' => 'NIK sudah terdaftar.',
            'email.unique' => 'Email sudah terdaftar.',
            'no_hp.unique' => 'No HP sudah terdaftar.',
            'no_hp.regex' => 'Format No HP tidak valid.',
        ];

        $data = $request->validate([
            'nama_lengkap' => 'required|string',
            'nama_panggilan' => 'nullable|string',
            'foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            'password' => 'required|string',
            'email' => 'required|email|unique:users',
            'no_hp' => 'required|string|unique:users|regex:/^[0-9]{10,13}$/',
            'nik' => 'required|string|unique:users',
            'foto_ktp' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            'provinsi' => 'required|string',
            'kabupaten' => 'required|string',
            'kecamatan' => 'required|string',
            'kelurahan' => 'required|string',
            'rt' => 'required|string',
            'rw' => 'required|string',
            'lrg' => 'required|string',
            'status' => 'required|string',
        ], $messages);

        $fotoPath = $request->file('foto')->store('public/foto_profil');
        $data['foto'] = $fotoPath;

        $fotoKtpPath = $request->file('foto_ktp')->store('public/foto_ktp');
        $data['foto_ktp'] = $fotoKtpPath;

        $data['password'] = Hash::make($data['password']);

        $referalCode = $request->input('referal_dari');
        $data['nama_lengkap'] = ucwords(strtolower($request->input('nama_lengkap')));
        $data['nama_panggilan'] = ucwords(strtolower($request->input('nama_panggilan')));

        try {

            $referal_dari = null;

            if ($referalCode) {
                $referrer = User::where('kode_referal', $referalCode)->first();
                if (!$referrer) {
                    return response()->json(['message' => 'Kode Referal Tidak Valid!'], 400);
                }
                $referal_dari = $referrer->id;
                $referrer->refresh();
                $totalReferals = $referrer->count();
                if ($totalReferals >= 50 && $referrer->status === 'Relawan') {
                    $referrer->status = 'TIM';
                    $referrer->save();
                }
            }
            $user = User::create($data);
            $user->referal_dari = $referal_dari;
            do {
                $kodeReferal = 'GES' . rand(100000, 999999);
            } while (User::where('kode_referal', $kodeReferal)->exists());

            $user->kode_referal = $kodeReferal;
            $user->save();

            $userData = [
                "id" => $user->id,
                "nama_lengkap" => $user->nama_lengkap,
                "nama_panggilan" => $user->nama_panggilan,
                "foto" => asset(Storage::url($user->foto)),
                "email" => $user->email,
                "no_hp" => $user->no_hp,
                "nik" => $user->nik,
                "foto_ktp" => asset(Storage::url($user->foto_ktp)),
                "provinsi" => $user->provinsi,
                "kabupaten" => $user->kabupaten,
                "kecamatan" => $user->kecamatan,
                "kelurahan" => $user->kelurahan,
                "rt" => $user->rt,
                "rw" => $user->rw,
                "lrg" => $user->lrg,
                "kode_referal" => $user->kode_referal,
                "referal_dari" => $user->referal_dari,
                "status" => $user->status,
            ];

            // $token = $user->createToken('authToken')->plainTextToken;
            return response()->json(['message' => 'Registrasi Berhasil!'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e], 400);
        }
    }

    public function getDownlines($userId)
    {
        $directDownlines = User::where('referal_dari', $userId)->get();

        $allDownlines = [];

        foreach ($directDownlines as $downline) {
            $subDownlines = $this->getDownlines($downline->id);
            $downlineData = [
                'user' => $downline,
                'total_downlines' => count($subDownlines),
                'downlines' => $subDownlines
            ];
            $allDownlines[] = $downlineData;
        }

        return $allDownlines;
    }

    public function countUsers()
    {
        $totalUsers = User::where('status', '!=', 'Admin')->count();
        $totalAdmin = User::where('status', 'Admin')->count();
        $totalRelawan = User::where('status', 'Relawan')->count();
        $totalTim = User::where('status', 'Tim')->count();

        return response()->json([
            'total_users' => $totalUsers,
            'total_admin' => $totalAdmin,
            'total_relawan' => $totalRelawan,
            'total_tim' => $totalTim,
        ], 200);
    }

    public function users()
    {
        $users = User::where('status', '!=', 'Admin')->get();
        return response()->json(['users' => $users], 200);
    }

    public function importUsers(Request $request)
    {
        $json = $request->input('data');

        if (!is_array($json)) {
            return response()->json(['message' => 'Data tidak valid.'], 400);
        }

        DB::beginTransaction();
        $failedData = [];

        try {
            foreach ($json as $item) {
                if (User::where('email', $item['email'])->exists() || User::where('no_hp', $item['no_hp'])->exists()) {
                    $duplicateReasons = [];

                    if (User::where('email', $item['email'])->exists()) {
                        $duplicateReasons[] = 'Email sudah terdaftar';
                    }

                    if (User::where('no_hp', $item['no_hp'])->exists()) {
                        $duplicateReasons[] = 'No HP sudah terdaftar';
                    }

                    if (User::where('nik', $item['nik'])->exists()) {
                        $duplicateReasons[] = 'NIK sudah terdaftar';
                    }

                    if (!empty($duplicateReasons)) {
                        $failedData[] = [
                            'Email' => $item['email'],
                            'No HP' => $item['no_hp'],
                            'NIK' => $item['nik'],
                            'Keterangan' => implode(', ', $duplicateReasons) . '.'
                        ];
                        continue;
                    }
                }
                do {
                    $kodeReferal = 'GES' . rand(100000, 999999);
                } while (User::where('kode_referal', $kodeReferal)->exists());

                $user = User::create([
                    "nama_lengkap" => $item['nama_lengkap'],
                    "nama_panggilan" => $item['nama_panggilan'],
                    "password" => Hash::make('password123'),
                    "foto" => $item['foto'],
                    "email" => $item['email'],
                    "no_hp" => $item['no_hp'],
                    "nik" => $item['nik'],
                    "foto_ktp" => $item['foto_ktp'],
                    "provinsi" => $item['provinsi'],
                    "kabupaten" => $item['kabupaten'],
                    "kecamatan" => $item['kecamatan'],
                    "kelurahan" => $item['kelurahan'],
                    "rt" => $item['rt'],
                    "rw" => $item['rw'],
                    "lrg" => $item['lrg'],
                    "status" => $item['status'],
                    "kode_referal" => $kodeReferal,
                ]);
                $referal_dari = null;

                if ($item['referal_dari']) {
                    $referrer = User::where('email', $item['referal_dari'])->first();
                    $referal_dari = $referrer->id;
                    $referrer->refresh();
                    $totalReferals = $referrer->count();
                    if ($totalReferals >= 50 && $referrer->status === 'Relawan') {
                        $referrer->status = 'TIM';
                        $referrer->save();
                    }
                }
                $user->referal_dari = $referal_dari;
                $user->update();
            }

            if (count($failedData) > 0) {
                DB::rollBack();
                return response()->json(['message' => 'Data gagal diimpor.', 'failed_data' => $failedData], 400);
            }

            DB::commit();
            return response()->json(['message' => 'Data berhasil diimpor.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ada kesalahan saat mengimpor data.', 'error' =>  $e->getMessage()], 500);
        }
    }

    public function addAdmin(Request $request)
    {
        $messages = [
            'required' => ':attribute harus diisi.',
            'string' => ':attribute harus berupa teks.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'no_hp.unique' => 'No HP sudah terdaftar.',
            'no_hp.regex' => 'Format No HP tidak valid.',
        ];

        $data = $request->validate([
            'nama_lengkap' => 'required|string',
            'nama_panggilan' => 'nullable|string',
            'foto' => 'required|string',
            'password' => 'required|string',
            'email' => 'required|email|unique:users',
            'no_hp' => 'required|string|unique:users|regex:/^[0-9]{10,13}$/',
            'nik' => 'required|string|unique:users',
            'foto_ktp' => 'required|string',
            'provinsi' => 'required|string',
            'kabupaten' => 'required|string',
            'kecamatan' => 'required|string',
            'kelurahan' => 'required|string',
            'kode_referal' => 'required|string',
            'rt' => 'required|string',
            'rw' => 'required|string',
            'lrg' => 'required|string',
            'status' => 'required|string',
        ], $messages);

        $data['password'] = Hash::make($data['password']);

        $data['nama_lengkap'] = ucwords(strtolower($request->input('nama_lengkap')));

        try {
            $user = User::create($data);

            $user->save();

            return response()->json(['message' => 'Registrasi Berhasil!'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e], 400);
        }
    }

    public function filterUser(Request $request)
    {
        $kabupaten = $request->input('kabupaten');
        $kecamatan = $request->input('kecamatan');
        $kelurahan = $request->input('kelurahan');
        $status = $request->input('status');

        $query = User::where('status', '!=', 'Admin');

        if ($kabupaten) {
            $query->where('kabupaten', $kabupaten);
        }

        if ($kecamatan) {
            $query->where('kecamatan', $kecamatan);
        }

        if ($kelurahan) {
            $query->where('kelurahan', $kelurahan);
        }

        if ($status && ($status == 'Tim' || $status == 'Relawan')) {
            $query->where('status', $status);
        }

        $users = $query->get();

        return response()->json(['users' => $users], 200);
    }

    public function getAdmins(Request $request)
    {

        $query = User::where('status', 'Admin');

        $admins = $query->get();

        return response()->json(['admins' => $admins], 200);
    }

    public function myDownlines()
    {
        $user = Auth::user();
        $downlines = $this->getDownlines($user->id);
        return response()->json(['downlines' => $downlines], 200);
    }

    public function userDownlines(Request $request)
    {
        $user = User::find($request->id);
        $downlines = $this->getDownlines($user->id);
        return response()->json(['downlines' => $downlines], 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'identity' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->identity)
            ->orWhere('no_hp', $request->identity)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid Credentials'], 401);
        }

        $userData = [
            "id" => $user->id,
            "nama_lengkap" => $user->nama_lengkap,
            "nama_panggilan" => $user->nama_panggilan,
            "foto" => asset(Storage::url($user->foto)),
            "email" => $user->email,
            "no_hp" => $user->no_hp,
            "nik" => $user->nik,
            "foto_ktp" => asset(Storage::url($user->foto_ktp)),
            "provinsi" => $user->provinsi,
            "kabupaten" => $user->kabupaten,
            "kecamatan" => $user->kecamatan,
            "kelurahan" => $user->kelurahan,
            "rt" => $user->rt,
            "rw" => $user->rw,
            "lrg" => $user->lrg,
            "kode_referal" => $user->kode_referal,
            "referal_dari" => $user->referal_dari,
            "status" => $user->status,
        ];

        $token = $user->createToken('appToken')->plainTextToken;

        return response()->json(['message' => 'Login Berhasil!', 'user' => $userData, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout Berhasil!']);
    }

    public function updateProfile(Request $request)
    {
        try {

            $user = Auth::user();
            $messages = [
                'required' => ':attribute harus diisi.',
                'email' => ':attribute harus berupa alamat email yang valid.',
                'email.unique' => 'Email sudah terdaftar.',
                'no_hp.unique' => 'No HP sudah terdaftar.',
                'no_hp.regex' => 'Format No HP tidak valid.',
            ];

            $data = $request->validate([
                'nama_lengkap' => 'required|string',
                'nama_panggilan' => 'nullable|string',
                'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'no_hp' => 'required|string|unique:users,no_hp,' . $user->id . '|regex:/^[0-9]{10,13}$/',
            ], $messages);

            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('public/foto_profil');
                $data['foto'] = $fotoPath;
            }

            $data['nama_lengkap'] = ucwords(strtolower($data['nama_lengkap']));
            $data['nama_panggilan'] = ucwords(strtolower($data['nama_panggilan']));

            $user->update($data);

            $userData = [
                "id" => $user->id,
                "nama_lengkap" => $user->nama_lengkap,
                "nama_panggilan" => $user->nama_panggilan,
                "foto" => asset(Storage::url($user->foto)),
                "email" => $user->email,
                "no_hp" => $user->no_hp,
                "nik" => $user->nik,
                "foto_ktp" => asset(Storage::url($user->foto_ktp)),
                "provinsi" => $user->provinsi,
                "kabupaten" => $user->kabupaten,
                "kecamatan" => $user->kecamatan,
                "kelurahan" => $user->kelurahan,
                "rt" => $user->rt,
                "rw" => $user->rw,
                "lrg" => $user->lrg,
                "kode_referal" => $user->kode_referal,
                "referal_dari" => $user->referal_dari,
                "status" => $user->status,
            ];

            return response()->json(['message' => 'Profil berhasil diperbarui', 'user' => $userData]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e], 400);
        }
    }

    public function updateUser(Request $request)
    {
        try {
            $user = User::findOrFail($request->id);

            $messages = [
                'required' => ':attribute harus diisi.',
                'email' => ':attribute harus berupa alamat email yang valid.',
                'email.unique' => 'Email sudah terdaftar.',
                'no_hp.unique' => 'No HP sudah terdaftar.',
                'nik.unique' => 'NIK sudah terdaftar.',
                'no_hp.regex' => 'Format No HP tidak valid.',
            ];

            $data = $request->validate([
                'nama_lengkap' => 'required|string',
                'nama_panggilan' => 'nullable|string',
                'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
                'foto_ktp' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'no_hp' => 'required|string|unique:users,no_hp,' . $user->id . '|regex:/^[0-9]{10,13}$/',
                'nik' => 'required|string|unique:users,nik,' . $user->id,
                'provinsi' => 'required|string',
                'kabupaten' => 'required|string',
                'kecamatan' => 'required|string',
                'kelurahan' => 'required|string',
                'rt' => 'required|string',
                'rw' => 'required|string',
                'lrg' => 'required|string',
                'status' => 'required|string',
            ], $messages);

            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('public/foto_profil');
                $data['foto'] = $fotoPath;
            }
            if ($request->hasFile('foto_ktp')) {
                $fotoPath = $request->file('foto_ktp')->store('public/foto_ktp');
                $data['foto_ktp'] = $fotoPath;
            }

            $data['nama_lengkap'] = ucwords(strtolower($data['nama_lengkap']));
            $data['nama_panggilan'] = ucwords(strtolower($data['nama_panggilan']));

            $user->update($data);

            $userData = [
                "id" => $user->id,
                "nama_lengkap" => $user->nama_lengkap,
                "nama_panggilan" => $user->nama_panggilan,
                "foto" => asset(Storage::url($user->foto)),
                "email" => $user->email,
                "no_hp" => $user->no_hp,
                "nik" => $user->nik,
                "foto_ktp" => asset(Storage::url($user->foto_ktp)),
                "provinsi" => $user->provinsi,
                "kabupaten" => $user->kabupaten,
                "kecamatan" => $user->kecamatan,
                "kelurahan" => $user->kelurahan,
                "rt" => $user->rt,
                "rw" => $user->rw,
                "lrg" => $user->lrg,
                "kode_referal" => $user->kode_referal,
                "referal_dari" => $user->referal_dari,
                "status" => $user->status,
            ];
            return response()->json(['message' => 'Profil berhasil diperbarui', 'userData' => $userData]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e], 400);
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User Berhasil Dihapus!'], 200);
    }

    public function me(Request $request)
    {
        try {
            $user = Auth::user();
            $userData = [
                "id" => $user->id,
                "nama_lengkap" => $user->nama_lengkap,
                "nama_panggilan" => $user->nama_panggilan,
                "foto" => asset(Storage::url($user->foto)),
                "email" => $user->email,
                "no_hp" => $user->no_hp,
                "nik" => $user->nik,
                "foto_ktp" => asset(Storage::url($user->foto_ktp)),
                "provinsi" => $user->provinsi,
                "kabupaten" => $user->kabupaten,
                "kecamatan" => $user->kecamatan,
                "kelurahan" => $user->kelurahan,
                "rt" => $user->rt,
                "rw" => $user->rw,
                "lrg" => $user->lrg,
                "kode_referal" => $user->kode_referal,
                "referal_dari" => $user->referal_dari,
                "status" => $user->status,
            ];

            return response()->json(['message' => 'User Didapatkan', 'user' => $userData]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e], 400);
        }
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $messages = [
            'required' => ':attribute harus diisi.',
            'string' => ':attribute harus berupa teks.',
            'confirmed' => ':attribute konfirmasi tidak cocok.',
            'min' => ':attribute setidaknya harus :min karakter.',
            'current_password' => 'Password saat ini salah.'
        ];

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('Password saat ini salah!');
                }
            }],
            'new_password' => 'required|string|min:8|confirmed',
        ], $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password berhasil diubah']);
    }
}
