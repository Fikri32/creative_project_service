<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use App\Models\Video;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class VideoController extends Controller
{
    public function index()
    {
        $videos = Video::all();
        return response()->json($videos);
    }

    public function store(Request $request)
    {
        set_time_limit(3600);
        Log::info('Request received for video upload', $request->all());

        // Validasi request
        $request->validate([
            'title' => 'required|string|max:255',
            'module_id' => 'required|integer',
            'duration' => 'required|integer',
            'url_video' => 'required|file|mimes:mp4,mkv,avi|max:2097152', // 2GB limit
        ]);

        // Initialize the file receiver
        $receiver = new FileReceiver("url_video", $request, HandlerFactory::classFromRequest($request));

        // Check if the file was uploaded
        if (!$receiver->isUploaded()) {
            Log::error('Upload missing file');
            throw new UploadMissingFileException();
        }

        // Receive the file and handle chunks
        $save = $receiver->receive();

        // Check if the upload is finished
        if ($save->isFinished()) {
            Log::info('Upload finished, saving file');
            return $this->saveFile($save->getFile(), $request);
        } else {
            // If upload is not finished, return progress
            $handler = $save->handler();
            $percentageDone = $handler->getPercentageDone();
            Log::info('Upload in progress', ['done' => $percentageDone]);

            return response()->json([
                "done" => $percentageDone
            ]);
        }
    }

    protected function saveFile(UploadedFile $file, Request $request)
    {
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $moduleFolder = 'module_' . $request->module_id;

        // Custom file name
        $customFileName = $request->title . '_' . $request->module_id . '.' . $extension;

        Log::info('Starting file upload', [
            'originalFilename' => $originalFilename,
            'extension' => $extension,
            'filename' => $filename,
            'moduleFolder' => $moduleFolder,
            'customFileName' => $customFileName,
        ]);

        // Storage path for the video
        $storagePath = storage_path('app/public/videos/' . $moduleFolder);
        Log::info('Storage path', ['path' => $storagePath]);

        // Create directory if not exists
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0777, true); // Create directory recursively with proper permissions
            Log::info('Directory created', ['path' => $storagePath]);
        } else {
            Log::info('Directory already exists', ['path' => $storagePath]);
        }

        // File path to save in storage
        $filePath = 'public/videos/' . $moduleFolder . '/' . $customFileName;
        Log::info('File path', ['path' => $filePath]);

        try {
            // Save the file to storage
            File::put(storage_path('app/' . $filePath), file_get_contents($file->getRealPath()));
            Log::info('File saved to storage', ['filePath' => $filePath]);
        } catch (\Exception $e) {
            // Handle file save error
            Log::error('Failed to save file', [
                'filePath' => $filePath,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to save file'], 500);
        }

        // Create video record in database
        $video = Video::create([
            'title' => $request->title,
            'url_video' => Storage::url($filePath),
            'duration' => $request->duration,
            'module_id' => $request->module_id,
        ]);

        Log::info('Video uploaded successfully', [
            'video' => $video,
            'path' => $filePath,
        ]);

        return response()->json($video, 201);
    }

    public function show($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        return response()->json($video);
    }

    public function update(Request $request, $id)
    {
        set_time_limit(3600);
        Log::info('Request received for video update', $request->all());

        // Validate request
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'module_id' => 'required|integer',
                'duration' => 'required|integer',
                'url_video' => 'nullable|file|mimes:mp4,mkv,avi|max:2097152', // 2GB limit
            ]);
            Log::info('Request validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error', ['error' => $e->errors()]);
            return response()->json(['error' => 'Validation error', 'details' => $e->errors()], 422);
        }

        // Find existing video record
        $video = Video::find($id);
        if (!$video) {
            Log::error('Video not found', ['id' => $id]);
            return response()->json(['error' => 'Video not found'], 404);
        }
        Log::info('Video record found', ['video' => $video]);

        // Update video attributes
        $video->title = $request->title;
        $video->duration = $request->duration;
        $video->module_id = $request->module_id;

        // Check if a file is uploaded
        if ($request->hasFile('url_video')) {
            Log::info('File upload detected');

            $oldFilePath = str_replace('/storage', 'public', $video->url_video);

            if (Storage::exists($oldFilePath)) {
                Storage::delete($oldFilePath);
                Log::info('Old video file deleted', ['path' => $oldFilePath]);
            }

            // Save the new file
            $file = $request->file('url_video');
            $filePath = $file->store('videos', 'public');
            $video->url_video = Storage::url($filePath);
        }

        $video->save();

        Log::info('Video updated successfully', [
            'video' => $video,
        ]);

        return response()->json($video, 200);
    }




    public function destroy($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        // Mendapatkan path relatif dari file
        $relativePath = str_replace('/storage', 'public', $video->url_video);

        // Menambahkan logging untuk memastikan path yang benar
        Log::info('Attempting to delete video file', ['path' => $relativePath]);

        if (Storage::delete($relativePath)) {
            Log::info('File deleted successfully', ['path' => $relativePath]);
        } else {
            Log::error('Failed to delete file', ['path' => $relativePath]);
        }

        $video->delete();

        return response()->json(['message' => 'Video deleted successfully']);
    }
}
