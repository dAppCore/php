import { defineConfig } from 'vitepress'
import { fileURLToPath } from 'url'
import path from 'path'
import { getPackagesSidebar, getPackagesNav } from './sidebar.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const docsDir = path.resolve(__dirname, '..')

// Auto-discover packages and build items
const packagesSidebar = getPackagesSidebar(docsDir)
const packagesNav = getPackagesNav(docsDir)

export default defineConfig({
  title: 'Host UK',
  description: 'Native application frameworks for PHP and Go',
  base: '/',

  ignoreDeadLinks: [
    // Ignore localhost links
    /^https?:\/\/localhost/,
    // Old paths during migration
    /\/packages\/core/,
    /\/packages\/(php|go)/,
    /\/core\//,
    /\/architecture\//,
    /\/patterns-guide\//,
    // Security pages moved to /build/php/
    /\/security\//,
    // Package pages not yet created
    /\/packages\/admin\/(tables|security|hlcrf|activity)/,
    /\/packages\/api\/(openapi|analytics|alerts|logging)/,
    /\/packages\/mcp\/commerce/,
    /\/build\/php\/(services|seeders|security|email-shield|action-gate|i18n)/,
    // Package root links (without trailing slash) - VitePress resolves these
    /^\/packages\/(admin|api|mcp|tenant|commerce|content|developer)$/,
    /^\/packages\/(admin|api|mcp|tenant|commerce|content|developer)#/,
    /^\/build\/(php|go)$/,
    /^\/build\/(php|go)#/,
    // Guide moved to /build/php/
    /\/guide\//,
    // Other pages not yet created
    /\/testing\//,
    /\/changelog/,
    /\/contributing/,
    // Go docs - relative paths (cmd moved to /build/cli/)
    /\.\.\/configuration/,
    /\.\.\/examples/,
    /\.\/cmd\//,
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      {
        text: 'Build',
        activeMatch: '/build/',
        items: [
          { text: 'PHP', link: '/build/php/' },
          { text: 'Go', link: '/build/go/' },
          { text: 'CLI', link: '/build/cli/' }
        ]
      },
      {
        text: 'Publish',
        activeMatch: '/publish/',
        items: [
          { text: 'Overview', link: '/publish/' },
          { text: 'GitHub', link: '/publish/github' },
          { text: 'Docker', link: '/publish/docker' },
          { text: 'npm', link: '/publish/npm' },
          { text: 'Homebrew', link: '/publish/homebrew' },
          { text: 'Scoop', link: '/publish/scoop' },
          { text: 'AUR', link: '/publish/aur' },
          { text: 'Chocolatey', link: '/publish/chocolatey' },
          { text: 'LinuxKit', link: '/publish/linuxkit' }
        ]
      },
      {
        text: 'Deploy',
        activeMatch: '/deploy/',
        items: [
          { text: 'Overview', link: '/deploy/' },
          { text: 'PHP', link: '/deploy/php' },
          { text: 'LinuxKit VMs', link: '/deploy/linuxkit' },
          { text: 'Templates', link: '/deploy/templates' },
          { text: 'Docker', link: '/deploy/docker' }
        ]
      },
      {
        text: 'Packages',
        items: packagesNav
      }
    ],

    sidebar: {
      // Packages index
      '/packages/': [
        {
          text: 'Packages',
          items: packagesNav.map(p => ({ text: p.text, link: p.link }))
        }
      ],

      // Publish section
      '/publish/': [
        {
          text: 'Publish',
          items: [
            { text: 'Overview', link: '/publish/' },
            { text: 'GitHub', link: '/publish/github' },
            { text: 'Docker', link: '/publish/docker' },
            { text: 'npm', link: '/publish/npm' },
            { text: 'Homebrew', link: '/publish/homebrew' },
            { text: 'Scoop', link: '/publish/scoop' },
            { text: 'AUR', link: '/publish/aur' },
            { text: 'Chocolatey', link: '/publish/chocolatey' },
            { text: 'LinuxKit', link: '/publish/linuxkit' }
          ]
        }
      ],

      // Deploy section
      '/deploy/': [
        {
          text: 'Deploy',
          items: [
            { text: 'Overview', link: '/deploy/' },
            { text: 'PHP', link: '/deploy/php' },
            { text: 'LinuxKit VMs', link: '/deploy/linuxkit' },
            { text: 'Templates', link: '/deploy/templates' },
            { text: 'Docker', link: '/deploy/docker' }
          ]
        }
      ],

      // Auto-discovered package sidebars (php, go, admin, api, mcp, etc.)
      ...packagesSidebar,

      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Authentication', link: '/api/authentication' },
            { text: 'Endpoints', link: '/api/endpoints' },
            { text: 'Errors', link: '/api/errors' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/host-uk' }
    ],

    footer: {
      message: 'Released under the EUPL-1.2 License.',
      copyright: 'Copyright © 2024-present Host UK'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/host-uk/core-php/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
