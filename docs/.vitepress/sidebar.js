import fs from 'fs'
import path from 'path'
import matter from 'gray-matter'

// Auto-discover packages from docs/packages/ and docs/build/
// Each package folder should have an index.md
//
// Frontmatter options:
//   title: "Page Title"           - Used in sidebar
//   sidebarTitle: "Short Title"   - Override for sidebar (optional)
//   order: 10                     - Sort order (lower = first)
//   collapsed: true               - Start group collapsed (for directories)

export function getPackagesSidebar(docsDir) {
  return {
    ...getSidebarForDir(docsDir, 'packages'),
    ...getSidebarForDir(docsDir, 'build'),
    ...getSidebarForDir(docsDir, 'publish'),
    ...getSidebarForDir(docsDir, 'deploy')
  }
}

function getSidebarForDir(docsDir, dirName) {
  const targetDir = path.join(docsDir, dirName)

  if (!fs.existsSync(targetDir)) {
    return {}
  }

  const sidebar = {}
  const packages = fs.readdirSync(targetDir, { withFileTypes: true })
    .filter(d => d.isDirectory())
    .map(d => d.name)
    .sort()

  for (const pkg of packages) {
    const pkgDir = path.join(targetDir, pkg)

    // Build sidebar tree recursively
    const items = buildSidebarItems(pkgDir, `/${dirName}/${pkg}`)

    if (items.length === 0) continue

    // Get package title from index.md
    let packageTitle = formatTitle(pkg)
    const indexPath = path.join(pkgDir, 'index.md')
    if (fs.existsSync(indexPath)) {
      const content = fs.readFileSync(indexPath, 'utf-8')
      const { data } = matter(content)
      if (data.title) {
        packageTitle = data.title
      } else {
        const h1Match = content.match(/^#\s+(.+)$/m)
        if (h1Match) packageTitle = h1Match[1]
      }
    }

    sidebar[`/${dirName}/${pkg}/`] = [
      {
        text: packageTitle,
        items: items
      }
    ]
  }

  return sidebar
}

// Recursively build sidebar items for a directory
function buildSidebarItems(dir, urlBase) {
  const entries = fs.readdirSync(dir, { withFileTypes: true })
  const items = []

  // Process files first, then directories
  const files = entries.filter(e => !e.isDirectory() && e.name.endsWith('.md'))
  const dirs = entries.filter(e => e.isDirectory())

  // Add markdown files
  for (const file of files) {
    const filePath = path.join(dir, file.name)
    const content = fs.readFileSync(filePath, 'utf-8')
    const { data } = matter(content)

    let title = data.sidebarTitle || data.title
    if (!title) {
      const h1Match = content.match(/^#\s+(.+)$/m)
      title = h1Match ? h1Match[1] : formatTitle(file.name.replace('.md', ''))
    }

    const isIndex = file.name === 'index.md'
    items.push({
      file: file.name,
      text: isIndex ? 'Overview' : title,
      link: isIndex ? `${urlBase}/` : `${urlBase}/${file.name.replace('.md', '')}`,
      order: data.order ?? (isIndex ? -1 : 100)
    })
  }

  // Add subdirectories as collapsed groups
  for (const subdir of dirs) {
    const subdirPath = path.join(dir, subdir.name)
    const subdirUrl = `${urlBase}/${subdir.name}`
    const subItems = buildSidebarItems(subdirPath, subdirUrl)

    if (subItems.length === 0) continue

    // Check for index.md in subdir for title/order
    let groupTitle = formatTitle(subdir.name)
    let groupOrder = 200
    let collapsed = true

    const indexPath = path.join(subdirPath, 'index.md')
    if (fs.existsSync(indexPath)) {
      const content = fs.readFileSync(indexPath, 'utf-8')
      const { data } = matter(content)
      if (data.sidebarTitle || data.title) {
        groupTitle = data.sidebarTitle || data.title
      } else {
        const h1Match = content.match(/^#\s+(.+)$/m)
        if (h1Match) groupTitle = h1Match[1]
      }
      if (data.order !== undefined) groupOrder = data.order
      if (data.collapsed !== undefined) collapsed = data.collapsed
    }

    items.push({
      text: groupTitle,
      collapsed: collapsed,
      items: subItems,
      order: groupOrder
    })
  }

  // Sort by order, then alphabetically
  items.sort((a, b) => {
    const orderA = a.order ?? 100
    const orderB = b.order ?? 100
    if (orderA !== orderB) return orderA - orderB
    return a.text.localeCompare(b.text)
  })

  // Remove order from final output
  return items.map(({ order, file, ...item }) => item)
}

// Get nav items for packages dropdown
export function getPackagesNav(docsDir) {
  const packagesDir = path.join(docsDir, 'packages')

  if (!fs.existsSync(packagesDir)) {
    return []
  }

  return fs.readdirSync(packagesDir, { withFileTypes: true })
    .filter(d => d.isDirectory())
    .filter(d => fs.existsSync(path.join(packagesDir, d.name, 'index.md')))
    .map(d => {
      const indexPath = path.join(packagesDir, d.name, 'index.md')
      const content = fs.readFileSync(indexPath, 'utf-8')
      const { data } = matter(content)

      let title = data.navTitle || data.title
      if (!title) {
        const h1Match = content.match(/^#\s+(.+)$/m)
        title = h1Match ? h1Match[1] : formatTitle(d.name)
      }

      return {
        text: title,
        link: `/packages/${d.name}/`,
        order: data.navOrder ?? 100
      }
    })
    .sort((a, b) => {
      if (a.order !== b.order) return a.order - b.order
      return a.text.localeCompare(b.text)
    })
    .map(({ text, link }) => ({ text, link }))
}

// Convert kebab-case to Title Case
function formatTitle(str) {
  return str
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
