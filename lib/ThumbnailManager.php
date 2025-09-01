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
                $thumbnails[] = '<span class="text-muted yform-usability-thumbnail-file">' . htmlspecialchars($singleFilename) . ' <small>(nicht gefunden)</small></span>';
                continue;
            }

            $isImage = $media->isImage();
            
            if ($isImage) {
                if ($thumbSize === 'rex_thumbnail_default') {
                    // Use REDAXO's default thumbnail with better sizing
                    $thumbUrl = rex_url::media($singleFilename);
                    $thumbnails[] = '<img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" title="' . htmlspecialchars($singleFilename) . '">';
                } else {
                    // Use Media Manager type
                    if (rex_addon::get('media_manager')->isAvailable()) {
                        $thumbUrl = rex_url::frontend('media/' . $thumbSize . '/' . $singleFilename);
                        $thumbnails[] = '<img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" title="' . htmlspecialchars($singleFilename) . '">';
                    } else {
                        // Fallback to original file
                        $thumbUrl = rex_url::media($singleFilename);
                        $thumbnails[] = '<img src="' . htmlspecialchars($thumbUrl) . '" alt="' . htmlspecialchars($singleFilename) . '" style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 3px; object-fit: cover;" title="' . htmlspecialchars($singleFilename) . '">';
                    }
                }
            } else {
                // Not an image - show filename with file type icon
                $extension = strtolower(pathinfo($singleFilename, PATHINFO_EXTENSION));
                $iconClass = match($extension) {
                    'pdf' => 'fa-file-pdf-o',
                    'doc', 'docx' => 'fa-file-word-o',
                    'xls', 'xlsx' => 'fa-file-excel-o',
                    'ppt', 'pptx' => 'fa-file-powerpoint-o',
                    'zip', 'rar', '7z' => 'fa-file-archive-o',
                    'mp4', 'avi', 'mov' => 'fa-file-video-o',
                    'mp3', 'wav', 'ogg' => 'fa-file-audio-o',
                    'txt' => 'fa-file-text-o',
                    default => 'fa-file-o'
                };
                $thumbnails[] = '<span class="text-muted yform-usability-thumbnail-file" style="display: inline-block; margin: 2px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; background: #f9f9f9; min-width: 60px; text-align: center;"><i class="fa ' . $iconClass . '"></i><br><small>' . htmlspecialchars(basename($singleFilename)) . '</small></span>';
            }
        }

        return '<div class="yform-usability-thumbnails">' . implode(' ', $thumbnails) . '</div>';
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
