<?php
/**
 * AttributeRegistry Plugin Configuration
 *
 * This file contains the default configuration for the AttributeRegistry plugin.
 * Copy this file to your application's config directory and modify as needed.
 */
return [
    'AttributeRegistry' => [
        /*
         * Cache configuration for storing discovered attributes.
         */
        'cache' => [
            /*
             * Whether caching is enabled.
             * When disabled, attributes will be re-discovered on every request.
             * This is useful for development but should be enabled in production.
             * Default: true
             */
            'enabled' => true,

            /*
             * Validate file hashes on cache retrieval.
             * When enabled, cached entries are validated against file content changes.
             * Useful in development to auto-rebuild cache when files change.
             * Set to Configure::read('debug') to enable in debug mode only.
             * Default: false
             */
            'validateFiles' => false,

            /*
             * Cache directory path for compiled attribute files.
             * Default: CACHE . 'attribute_registry' . DS
             */
            'path' => null,
        ],

        /*
         * Scanner configuration for discovering attributes in PHP files.
         */
        'scanner' => [
            /*
             * Glob patterns for files to scan.
             * These patterns are relative to each base path (app root + plugin paths).
             * Supports ** for recursive directory matching.
             * Default: ['src/**\/*.php']
             */
            'paths' => [
                'src/**/*.php',
            ],

            /*
             * Glob patterns for paths to exclude from scanning.
             * Files matching these patterns will be skipped.
             * Default: ['vendor/**', 'tmp/**', 'logs/**', 'tests/**', 'webroot/**']
             */
            'exclude_paths' => [
                'vendor/**',
                'tmp/**',
                'logs/**',
                'tests/**',
                'webroot/**',
            ],

            /*
             * Attribute class names to exclude from discovery results.
             * Supports exact FQCN matches and namespace wildcards.
             *
             * Examples:
             * - 'Override' - Exclude PHP's built-in Override attribute
             * - 'App\Internal\*' - Exclude all attributes in App\Internal namespace
             * - 'Doctrine\ORM\Mapping\*' - Exclude all Doctrine ORM attributes
             *
             * Default: []
             */
            'exclude_attributes' => [
                // 'Override',
                // 'Deprecated',
                // 'App\Internal\*',
            ],
        ],
    ],
];
