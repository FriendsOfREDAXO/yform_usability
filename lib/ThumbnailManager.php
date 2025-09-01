<?php

/**
 * This file is part of the yform/usability package.
 *
 * @author Friends Of REDAXO
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability;

use rex;
use rex_addon;
use rex_media;
use rex_sql;
use rex_url;

class ThumbnailManager
{
    private static array $mappings = [];
    private static bool $mappingsLoaded = false;

    /**
     * Get thumbnail mappings for a specific table
     */
    public static function getMappingsForTable(string $tableName): array
    {
        self::loadMappings();
        return self::$mappings[$tableName] ?? [];
    }

    /**
     * Get thumbnail mapping for a specific table and column
     */
    public static function getMapping(string $tableName, string $columnName): ?array
    {
        self::loadMappings();
        return self::$mappings[$tableName][$columnName] ?? null;
    }

    /**
     * Check if a column has thumbnail mapping
     */
    public static function hasMapping(string $tableName, string $columnName): bool
    {
        return self::getMapping($tableName, $columnName) !== null;
    }

    /**
     * Load all thumbnail mappings from database
     */
    private static function loadMappings(): void
    {
        if (self::$mappingsLoaded) {
            return;
        }

        self::$mappings = [];
        
        $sql = rex_sql::factory();
        try {
            $sql->setQuery('SELECT table_name, column_name, thumb_size FROM ' . rex::getTable('yform_usability_thumbnails'));
            
            while ($sql->hasNext()) {
                $tableName = $sql->getValue('table_name');
                $columnName = $sql->getValue('column_name');
                $thumbSize = $sql->getValue('thumb_size');
                
                if (!isset(self::$mappings[$tableName])) {
                    self::$mappings[$tableName] = [];
                }
                
                self::$mappings[$tableName][$columnName] = [
                    'thumb_size' => $thumbSize
                ];
                
                $sql->next();
            }
        } catch (\rex_sql_exception $e) {
            // Table might not exist yet during installation
            self::$mappings = [];
        }
        
        self::$mappingsLoaded = true;
    }

    /**
     * Generate thumbnail HTML for a media filename
     */
    public static function generateThumbnailHtml(string $filename, string $thumbSize = 'rex_thumbnail_default'): string
    {
        if (empty($filename)) {
            return '<span class="text-muted">-</span>';
        }

        // Handle comma-separated filenames (multiple files)
        $filenames = array_map('trim', explode(',', $filename));
        $thumbnails = [];

        foreach ($filenames as $singleFilename) {
            if (empty($singleFilename)) {
                continue;
            }

            $media = rex_media::get($singleFilename);
            if (!$media) {
                $thumbnails[] = '<span class="text-muted yform-usability-thumbnail-file" title="' . htmlspecialchars($singleFilename) . ' (nicht gefunden)">' . htmlspecialchars($singleFilename) . ' <small>(nicht gefunden)</small></span>';
                continue;
            }

            $isImage = $media->isImage();
            $mediaId = $media->getId();
            $categoryId = $media->getCategoryId();
            
            // REDAXO-style popup URL with media ID
            $mediapoolUrl = rex_url::backendController([
                'page' => 'mediapool/media', 
                'file_id' => $mediaId, 
                'rex_file_category' => $categoryId
            ]);
            
            if ($isImage) {
                if ($thumbSize === 'rex_thumbnail_default') {
                    // Use REDAXO's default thumbnail with better sizing
                    $thumbUrl = rex_url::media($singleFilename);
                    $thumbnails[] = '<a href="javascript:void(0)" onclick="openMediaDetails(\'\', ' . $mediaId . ', ' . $categoryId . ')" title="' . htmlspecialchars($singleFilename) . ' - Im Medienpool bearbeiten" class="yform-usability-thumbnail-image-link"><img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" class="yform-usability-thumbnail-image"></a>';
                } else {
                    // Use Media Manager type
                    if (rex_addon::get('media_manager')->isAvailable()) {
                        $thumbUrl = rex_url::frontend('media/' . $thumbSize . '/' . $singleFilename);
                        $thumbnails[] = '<a href="javascript:void(0)" onclick="openMediaDetails(\'\', ' . $mediaId . ', ' . $categoryId . ')" title="' . htmlspecialchars($singleFilename) . ' - Im Medienpool bearbeiten" class="yform-usability-thumbnail-image-link"><img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" class="yform-usability-thumbnail-image"></a>';
                    } else {
                        // Fallback to original file
                        $thumbUrl = rex_url::media($singleFilename);
                        $thumbnails[] = '<a href="javascript:void(0)" onclick="openMediaDetails(\'\', ' . $mediaId . ', ' . $categoryId . ')" title="' . htmlspecialchars($singleFilename) . ' - Im Medienpool bearbeiten" class="yform-usability-thumbnail-image-link"><img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" class="yform-usability-thumbnail-image"></a>';
                    }
                }
            } else {
                // Not an image - show Font Awesome 6 icon with media pool link
                $extension = strtolower(pathinfo($singleFilename, PATHINFO_EXTENSION));
                $iconClass = self::getFileIcon($extension);
                $thumbnails[] = '<a href="javascript:void(0)" onclick="openMediaDetails(\'\', ' . $mediaId . ', ' . $categoryId . ')" title="' . htmlspecialchars($singleFilename) . ' - Im Medienpool bearbeiten" class="yform-usability-thumbnail-file-link" style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; background: #f8f9fa; text-decoration: none; color: #6c757d; transition: all 0.2s;"><i class="' . $iconClass . '" style="font-size: 24px;"></i></a>';
            }
        }

        return '<div class="yform-usability-thumbnails">' . implode(' ', $thumbnails) . '</div>';
    }

    /**
     * Get Font Awesome 6 icon class for file extension
     */
    private static function getFileIcon(string $extension): string
    {
        return match($extension) {
            // Documents
            'pdf' => 'fa-regular fa-file-pdf',
            'doc', 'docx' => 'fa-regular fa-file-word',
            'xls', 'xlsx' => 'fa-regular fa-file-excel',
            'ppt', 'pptx' => 'fa-regular fa-file-powerpoint',
            'txt', 'rtf' => 'fa-regular fa-file-lines',
            
            // Archives
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2' => 'fa-regular fa-file-zipper',
            
            // Media
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv' => 'fa-regular fa-file-video',
            'mp3', 'wav', 'ogg', 'flac', 'aac', 'wma' => 'fa-regular fa-file-audio',
            'svg' => 'fa-regular fa-file-image',
            
            // Code
            'html', 'htm' => 'fa-regular fa-file-code',
            'css', 'scss', 'sass', 'less' => 'fa-regular fa-file-code',
            'js', 'ts', 'jsx', 'tsx' => 'fa-regular fa-file-code',
            'php', 'py', 'rb', 'java', 'c', 'cpp', 'cs' => 'fa-regular fa-file-code',
            'xml', 'json', 'yml', 'yaml' => 'fa-regular fa-file-code',
            
            // Images (shouldn't occur here, but just in case)
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff' => 'fa-regular fa-file-image',
            
            // Default
            default => 'fa-regular fa-file'
        };
    }

    /**
     * Clear cached mappings
     */
    public static function clearCache(): void
    {
        self::$mappings = [];
        self::$mappingsLoaded = false;
    }
}
