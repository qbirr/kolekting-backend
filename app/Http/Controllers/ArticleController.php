<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Article;

class ArticleController extends Controller
{

    public function index()
    {
        $articles = Article::all();
        return response()->json(['message' => 'Artikel Berhasil Diambil!', 'articles' => $articles], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'gambar' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            'konten' => 'required|string',
            'judul' => 'required|string',
        ]);

        $gambarPath = $request->file('gambar')->store('public/artikel');
        $data['gambar'] = $gambarPath;

        $article = Article::create($data);
        return response()->json(['message' => 'Artikel Berhasil Dibuat!', 'articles' => $article], 201);
    }

    public function show($id)
    {
        $article = Article::findOrFail($id);
        return response()->json($article);
    }

    public function update(Request $request)
    {
        try {
            $article = Article::findOrFail($request->id);
            $data = $request->validate([
                'gambar' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
                'konten' => 'required|string',
                'judul' => 'required|string',

            ]);
            if ($request->hasFile('gambar')) {
                $fotoPath = $request->file('gambar')->store('public/artikel');
                $data['gambar'] = $fotoPath;
            }

            $article->update($data);
            return response()->json(['message' => 'Artikel Berhasil Diubah!', 'articles' => $article]);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th]);
            //throw $th;
        }
        }

    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();
        return response()->json(['message' => 'Artikel Berhasil Dihapus!'], 200);
    }

}
