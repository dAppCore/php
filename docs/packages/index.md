---
title: Packages
---

<script setup>
import { ref, computed } from 'vue'

const search = ref('')

const packages = [
  {
    name: 'PHP Framework',
    slug: 'php',
    description: 'Modular monolith framework for Laravel with lifecycle events, multi-tenancy, and module system',
    icon: '🐘',
    featured: true
  },
  {
    name: 'Go Framework',
    slug: 'go',
    description: 'Native desktop application framework with Wails v3, services, lifecycle management, and CLI tools',
    icon: '🔷',
    featured: true
  },
  {
    name: 'Tenant',
    slug: 'tenant',
    description: 'Multi-tenancy, workspaces, user management, and entitlement systems',
    icon: '🏢'
  },
  {
    name: 'Admin',
    slug: 'admin',
    description: 'Admin panel with Livewire modals, forms, and global search',
    icon: '🎛️'
  },
  {
    name: 'API',
    slug: 'api',
    description: 'REST API framework with authentication, rate limiting, and webhooks',
    icon: '🔌'
  },
  {
    name: 'MCP',
    slug: 'mcp',
    description: 'Model Context Protocol server for AI agent integration',
    icon: '🤖'
  },
  {
    name: 'Commerce',
    slug: 'commerce',
    description: 'Billing, subscriptions, and payment processing with BTCPay and Stripe',
    icon: '💳'
  },
  {
    name: 'Content',
    slug: 'content',
    description: 'Headless CMS with AI generation, revision history, and webhooks',
    icon: '📝'
  },
  {
    name: 'Developer',
    slug: 'developer',
    description: 'Admin developer tools for debugging, monitoring, and server management',
    icon: '🛠️'
  }
]

const filtered = computed(() => {
  if (!search.value) return packages
  const q = search.value.toLowerCase()
  return packages.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.description.toLowerCase().includes(q)
  )
})

const featured = computed(() => filtered.value.filter(p => p.featured))
const ecosystem = computed(() => filtered.value.filter(p => !p.featured))
</script>

<style scoped>
.search-box {
  width: 100%;
  padding: 12px 16px;
  font-size: 16px;
  border: 1px solid var(--vp-c-border);
  border-radius: 8px;
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  margin-bottom: 24px;
}
.search-box:focus {
  outline: none;
  border-color: var(--vp-c-brand-1);
}
.search-box::placeholder {
  color: var(--vp-c-text-3);
}
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--vp-c-text-2);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 12px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}
.grid-featured {
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
}
.card {
  border: 1px solid var(--vp-c-border);
  border-radius: 12px;
  padding: 20px;
  background: var(--vp-c-bg-soft);
  transition: all 0.2s ease;
  text-decoration: none;
  display: block;
}
.card:hover {
  border-color: var(--vp-c-brand-1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}
.card-featured {
  border-color: var(--vp-c-brand-2);
  background: linear-gradient(135deg, var(--vp-c-bg-soft) 0%, var(--vp-c-bg) 100%);
}
.card-icon {
  font-size: 32px;
  margin-bottom: 12px;
}
.card-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--vp-c-text-1);
  margin-bottom: 8px;
}
.card-desc {
  font-size: 14px;
  color: var(--vp-c-text-2);
  line-height: 1.5;
}
.no-results {
  text-align: center;
  color: var(--vp-c-text-3);
  padding: 40px;
}
</style>

# Packages

Browse the Host UK package ecosystem.

<input
  v-model="search"
  type="text"
  class="search-box"
  placeholder="Search packages..."
/>

<div v-if="featured.length" class="section">
  <div class="section-title">Frameworks</div>
  <div class="grid grid-featured">
    <a v-for="pkg in featured" :key="pkg.slug" :href="`/build/${pkg.slug}/`" class="card card-featured">
      <div class="card-icon">{{ pkg.icon }}</div>
      <div class="card-title">{{ pkg.name }}</div>
      <div class="card-desc">{{ pkg.description }}</div>
    </a>
  </div>
</div>

<div v-if="ecosystem.length" class="section">
  <div class="section-title">Ecosystem</div>
  <div class="grid">
    <a v-for="pkg in ecosystem" :key="pkg.slug" :href="`./${pkg.slug}/`" class="card">
      <div class="card-icon">{{ pkg.icon }}</div>
      <div class="card-title">{{ pkg.name }}</div>
      <div class="card-desc">{{ pkg.description }}</div>
    </a>
  </div>
</div>

<div v-if="!featured.length && !ecosystem.length" class="no-results">
  No packages match "{{ search }}"
</div>
