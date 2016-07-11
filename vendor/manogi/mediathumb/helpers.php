<?php

use Intervention\Image\ImageManagerStatic as Image;
use Cms\Classes\MediaLibrary;

// Get the thumb
function getMediathumb($img, $mode = null, $size = null, $quality = null, $folder = null)
{
    // Return empty string if $img is falsy
    if (!$img) {
        return '';
    }

    // Set value if the parameter is not exists
    if (!$mode) {
        $mode = config('manogi.mediathumb::default.mode');
    }
    if (!$size) {
        $size = config('manogi.mediathumb::default.size');
    }
    if (!$quality) {
        $quality = config('manogi.mediathumb::default.quality');
    }
    if (!$folder) {
        $folder = config('manogi.mediathumb::default.folder');
    }

    // Add slash at the beginning if omitted
    if (substr($img, 0, 1) != '/') {
        $img = '/'.$img;
    }

    // Add slash by the end if omitted
    if (substr($folder, -1, 1) != '/') {
        $folder = $folder.'/';
    }

    // Path of CMS
    $disk = config('cms.storage.media.disk');
    $disk_folder = config('cms.storage.media.folder');

    $original_path = $disk_folder.$img;

    // Return empty string if file does not exist
    if (!Storage::disk($disk)->exists($original_path)) {
        return '';
    }

    // Get the image as data
    $original_file = Storage::disk($disk)->get($original_path);

    // Define directory for thumbnail
    $thumb_directory = $disk_folder.'/'.$folder;

    // Make new filename for folder names and filename
    $new_filename = str_replace('/', '-', substr($img, 1));

    // Store position of the dot before the extension
    $last_dot_position = strrpos($new_filename, '.');

    // Get the extension
    $extension = substr($new_filename, $last_dot_position + 1);

    // Get the new filename without extension
    $filename_body = substr($new_filename, 0, $last_dot_position);

    // Get filesize and filetime for extending the filename for the purpose of
    // creating a new thumb in case a new file with the same name is uploaded
    // (meaning the orginal file is overwritten)
    $filesize = Storage::disk($disk)->size($original_path);
    $filetime = Storage::disk($disk)->lastModified($original_path);

    // Make the string to add to the filname to for 2 purposes:
    // A) to make sure the that for the SAME image a thumbnail is only generated once
    // b) to make sure that a new thumb is generated if the original is overwritten
    $version_string = $mode.'-'.$size.'-'.$quality.'-'.$filesize.'-'.$filetime;

    // Create the complete new filename and hash the version string to make it shorter
    $new_filename = $filename_body.'-'.md5($version_string).'.'.$extension;

    // Define complete path of the new file (without the root path)
    $new_path = $thumb_directory.$new_filename;

    // Create the thumb directory if it does not exist
    if (!Storage::disk($disk)->exists($thumb_directory)) {
        Storage::disk($disk)->makeDirectory($thumb_directory);
    }

    // Create the thumb, but only if it does not exist
    if (!Storage::disk($disk)->exists($new_path)) {
        $image = Image::make($original_file);

        $final_mode = $mode;
        if ($mode == 'auto') {
            $final_mode = 'width';

            $ratio = $image->width() / $image->height();
            if ($ratio < 1) {
                $final_mode = 'height';
            }
        }
        if ($final_mode == 'width') {
            $image->resize($size, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        elseif ($final_mode == 'height') {
            $image->resize(null, $size, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        $image_stream = $image->stream($extension, $quality);
        Storage::disk($disk)->put($new_path, $image_stream->__toString());
    }

    return MediaLibrary::instance()->getPathUrl($folder.$new_filename);
}

// Alias for getMediathumb()
function mediathumbGetThumb($img, $mode = null, $size = null, $quality = null, $folder = null)
{
    return getMediathumb($img, $mode, $size, $quality, $folder);
}
