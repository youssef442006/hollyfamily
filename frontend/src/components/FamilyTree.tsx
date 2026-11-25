'use client';
import Link from 'next/link';
import { useMemo } from 'react';
import { kinshipTypes } from '@/lib/translations';

interface TreeData {
  root_ids: number[];
  persons: Record<string, { id: number; name: string; gender?: string | null; is_deceased?: boolean }>;
  edges: { from: number; to: number; label: string }[];
}

interface TreeNode {
  id: number;
  name: string;
  isDeceased: boolean;
  spouses: { id: number; name: string }[];
  children: TreeNode[];
}

function buildForest(tree: TreeData): TreeNode[] {
  const persons = tree.persons || {};
  const edges = tree.edges || [];
  const spouseOf = new Map<number, number[]>();
  const kidsOf = new Map<number, number[]>();
  edges.forEach(e => {
    if (e.label === 'husband' || e.label === 'wife') {
      const arr = spouseOf.get(e.from) || [];
      arr.push(e.to);
      spouseOf.set(e.from, arr);
    } else if (e.label === 'son' || e.label === 'daughter') {
      const arr = kidsOf.get(e.from) || [];
      arr.push(e.to);
      kidsOf.set(e.from, arr);
    }
  });
  const visited = new Set<number>();
  const buildNode = (id: number): TreeNode | null => {
    if (visited.has(id)) return null;
    const p = persons[String(id)];
    if (!p) return null;
    visited.add(id);
    const spouses = (spouseOf.get(id) || [])
      .filter(sid => persons[String(sid)] && !visited.has(sid))
      .map(sid => {
        visited.add(sid);
        return { id: sid, name: persons[String(sid)].name };
      });
    const children: TreeNode[] = [];
    (kidsOf.get(id) || []).forEach(kid => {
      const n = buildNode(kid);
      if (n) children.push(n);
    });
    return { id, name: p.name || `#${id}`, isDeceased: !!p.is_deceased, spouses, children };
  };
  const roots = [...(tree.root_ids || [])];
  Object.keys(persons).forEach(k => {
    const n = Number(k);
    if (!roots.includes(n)) roots.push(n);
  });
  const forest: TreeNode[] = [];
  roots.forEach(r => {
    const n = buildNode(r);
    if (n) forest.push(n);
  });
  return forest;
}

function RenderNode({ node }: { node: TreeNode }) {
  return (
    <div className="mr-2">
      <span className="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm">
        <Link href={`/persons/${node.id}`} className="text-church-primary hover:underline font-medium">
          {node.name}
        </Link>
        {node.isDeceased && <span className="text-xs text-gray-400">(متوفي)</span>}
        {node.spouses.map(s => (
          <span key={s.id} className="text-sm text-gray-500">
            ❤ <Link href={`/persons/${s.id}`} className="text-church-primary hover:underline">{s.name}</Link>
          </span>
        ))}
      </span>
      {node.children.length > 0 && (
        <div className="mr-8 mt-2 space-y-2 border-r-2 border-gray-300 pr-4">
          {node.children.map(c => (
            <RenderNode key={c.id} node={c} />
          ))}
        </div>
      )}
    </div>
  );
}

function countNodes(forest: TreeNode[]): number {
  let n = 0;
  const walk = (nodes: TreeNode[]) => nodes.forEach(x => { n += 1 + x.spouses.length; walk(x.children); });
  walk(forest);
  return n;
}

export default function FamilyTree({ tree }: { tree: TreeData | null }) {
  const forest = useMemo(() => (tree ? buildForest(tree) : []), [tree]);
  if (!tree || Object.keys(tree.persons || {}).length === 0) {
    return <p className="text-gray-500 text-center py-8">لا توجد بيانات شجرة</p>;
  }
  if (forest.length === 0) {
    return <p className="text-gray-500 text-center py-8">تعذر بناء عرض الشجرة من البيانات الحالية</p>;
  }
  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-500">عدد الأشخاص في الشجرة: {countNodes(forest)}</p>
      {forest.map(n => (
        <RenderNode key={n.id} node={n} />
      ))}
      <div className="mt-6 pt-4 border-t text-sm text-gray-500">
        <p className="font-bold mb-2">دليل الصلات:</p>
        <div className="flex flex-wrap gap-2">
          {Object.entries(kinshipTypes).map(([k, v]) => (
            <span key={k} className="badge-blue">{v}</span>
          ))}
        </div>
      </div>
    </div>
  );
}
