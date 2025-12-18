import type { AccessMenuDataItem } from '@/config/menu';
import type { AccountType } from '@/types/auth';

/**
 * Check if a user has access to a menu item
 */
function hasAccess(access: AccountType | AccountType[] | undefined, accountType: AccountType): boolean {
  if (!access) return true;
  if (Array.isArray(access)) {
    return access.includes(accountType);
  }
  return access === accountType;
}

/**
 * Filter menu items by user's account type
 * - Items without access field are visible to all
 * - Items with access field are only visible to matching account types
 * - Parent items are hidden if all children are filtered out
 */
export function filterMenuByAccess(
  menus: AccessMenuDataItem[],
  accountType: AccountType
): AccessMenuDataItem[] {
  const result: AccessMenuDataItem[] = [];

  for (const item of menus) {
    // Filter children first if they exist
    const filteredChildren = item.children
      ? filterMenuByAccess(item.children, accountType)
      : undefined;

    // If this item has children but all were filtered out, hide the parent
    if (item.children && filteredChildren?.length === 0) {
      continue;
    }

    // Check access for this item
    if (!hasAccess(item.access, accountType)) {
      continue;
    }

    result.push({
      ...item,
      children: filteredChildren,
    });
  }

  return result;
}

/**
 * Get all paths that require specific access
 * Used for route-level access control
 */
export function getAccessMap(menus: AccessMenuDataItem[]): Map<string, AccountType | AccountType[]> {
  const accessMap = new Map<string, AccountType | AccountType[]>();

  function traverse(items: AccessMenuDataItem[]) {
    for (const item of items) {
      if (item.path && item.access) {
        accessMap.set(item.path, item.access);
      }
      if (item.children) {
        traverse(item.children);
      }
    }
  }

  traverse(menus);
  return accessMap;
}

/**
 * Check if a user can access a specific path
 */
export function canAccessPath(
  path: string,
  accountType: AccountType,
  accessMap: Map<string, AccountType | AccountType[]>
): boolean {
  const access = accessMap.get(path);
  return hasAccess(access, accountType);
}