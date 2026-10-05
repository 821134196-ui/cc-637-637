import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api';

export default function DatasetDetail({ user }) {
  const { id } = useParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api(`/catalog/${id}`).then(setData).catch((e) => setError(e.message));
  }, [id]);

  if (error) return <p className="error">{error}</p>;
  if (!data) return <p>加载中…</p>;

  const { dataset, preview } = data;
  const columns = preview.length ? Object.keys(preview[0]) : [];

  return (
    <section>
      <h1>{dataset.title}</h1>
      <p>{dataset.description}</p>
      <p className="meta">{dataset.department} · 最新版本 v{dataset.latest_version}</p>

      <h2>公开字段</h2>
      <table>
        <thead><tr><th>字段</th><th>说明</th></tr></thead>
        <tbody>
          {dataset.public_fields.map((f) => (
            <tr key={f.name}><td>{f.label || f.name}</td><td>{f.description || '—'}</td></tr>
          ))}
        </tbody>
      </table>
      <p className="hint">受限字段不在公开预览中显示；登录后可在申请表单中查看字段清单并申请。</p>

      <h2>数据预览（前 {preview.length} 行，仅公开字段）</h2>
      <table>
        <thead><tr>{columns.map((c) => <th key={c}>{c}</th>)}</tr></thead>
        <tbody>
          {preview.map((row, i) => (
            <tr key={i}>{columns.map((c) => <td key={c}>{row[c]}</td>)}</tr>
          ))}
        </tbody>
      </table>

      {user
        ? <Link className="button" to={`/apply/${dataset.id}`}>申请访问原始数据</Link>
        : <p><Link to="/login">登录</Link> 后可提交访问申请。</p>}
    </section>
  );
}
