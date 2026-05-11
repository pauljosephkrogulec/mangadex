"use client";

import { useEffect, useState } from "react";
import api, { handleResponse } from "@/lib/api";
import type { HydraCollection, Tag } from "@/lib/types";

interface TagFilterProps {
  selected: string[];
  onChange: (selected: string[]) => void;
}

interface TagGroup {
  groupName: string;
  tags: Tag[];
}

export default function TagFilter({ selected, onChange }: TagFilterProps) {
  const [groups, setGroups] = useState<TagGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set());

  useEffect(() => {
    let cancelled = false;

    async function fetchTags() {
      setLoading(true);
      setError(null);

      const result = await handleResponse(
        api.get<HydraCollection<Tag>>("/tags?order[name]=asc"),
      );

      if (cancelled) return;

      if (result.success) {
        // Group tags by groupName
        const grouped = new Map<string, Tag[]>();
        for (const tag of result.data.member) {
          const existing = grouped.get(tag.groupName) ?? [];
          existing.push(tag);
          grouped.set(tag.groupName, existing);
        }

        // Convert to sorted array of groups
        const groupArray: TagGroup[] = Array.from(grouped.entries())
          .map(([groupName, tags]) => ({
            groupName,
            tags: tags.sort((a, b) => a.name.localeCompare(b.name)),
          }))
          .sort((a, b) => a.groupName.localeCompare(b.groupName));

        setGroups(groupArray);
      } else {
        setError(result.error);
      }

      setLoading(false);
    }

    fetchTags();
    return () => {
      cancelled = true;
    };
  }, []);

  const toggleTag = (tagId: string) => {
    const next = selected.includes(tagId)
      ? selected.filter((id) => id !== tagId)
      : [...selected, tagId];
    onChange(next);
  };

  const toggleGroup = (groupName: string) => {
    setCollapsedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupName)) {
        next.delete(groupName);
      } else {
        next.add(groupName);
      }
      return next;
    });
  };

  // Loading state
  if (loading) {
    return (
      <div>
        <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
          Tags
        </h3>
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="space-y-1.5">
              <div className="h-4 w-20 bg-md-surface-hover rounded animate-pulse" />
              <div className="space-y-1 pl-2">
                {Array.from({ length: 4 }).map((_, j) => (
                  <div key={j} className="h-3.5 w-28 bg-md-surface-hover rounded animate-pulse" />
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div>
        <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
          Tags
        </h3>
        <p className="text-xs text-md-text-secondary">Failed to load tags.</p>
      </div>
    );
  }

  // Empty state
  if (groups.length === 0) {
    return (
      <div>
        <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
          Tags
        </h3>
        <p className="text-xs text-md-text-secondary">No tags available.</p>
      </div>
    );
  }

  return (
    <div>
      <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
        Tags
      </h3>
      <div className="space-y-3">
        {groups.map((group) => {
          const isCollapsed = collapsedGroups.has(group.groupName);

          return (
            <div key={group.groupName}>
              {/* Group header */}
              <button
                onClick={() => toggleGroup(group.groupName)}
                className="flex items-center gap-1.5 w-full text-left text-xs font-medium text-md-text-secondary uppercase tracking-wide hover:text-md-text-primary transition-colors mb-1"
                aria-expanded={!isCollapsed}
              >
                <svg
                  className={`w-3 h-3 transition-transform ${isCollapsed ? "" : "rotate-90"}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 5l7 7-7 7"
                  />
                </svg>
                {group.groupName}
                <span className="text-[10px] text-md-text-secondary ml-auto">
                  {group.tags.length}
                </span>
              </button>

              {/* Tag checkboxes */}
              {!isCollapsed && (
                <div className="space-y-0.5 pl-4">
                  {group.tags.map((tag) => {
                    const isSelected = selected.includes(tag.id);
                    return (
                      <label
                        key={tag.id}
                        className="flex items-center gap-2 py-0.5 cursor-pointer group"
                      >
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => toggleTag(tag.id)}
                          className="w-3.5 h-3.5 rounded border-md-border bg-md-surface text-md-accent focus:ring-md-accent/30 focus:ring-offset-0 cursor-pointer accent-md-accent"
                        />
                        <span
                          className={`text-xs transition-colors ${
                            isSelected
                              ? "text-md-accent font-medium"
                              : "text-md-text-secondary group-hover:text-md-text-primary"
                          }`}
                        >
                          {tag.name}
                        </span>
                      </label>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
