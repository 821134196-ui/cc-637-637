import React, { useEffect, useState } from 'react';
import { api } from '../api';

export default function Citations({ user }) {
  const [citations, setCitations] = useState([]);
  const [grants, setGrants] = useState([]);
  const [form, setForm] = useState({ grant_id: '', citation_number: '', title: '', venue: '', published_at: '' });
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');

  const load = () => {
    api('/citations').then((d) => setCitations(d.citations)).catch((e) => setError(e.message));
    if (user) api('/grants').then((d) => setGrants(d.grants)).catch(() => {});
  };
  useEffect(() => { load(); }, [user]);

  const submit = async (e) => {
    e.preventDefault();
    setError(''); setOk('');
    try {
      await api('/citations', {
        method: 'POST',
        body: { ...form, grant_id: Number(form.grant_id), published_at: form.published_at || null },
      });
      setOk('登记成功');
      setForm({ grant_id: '', citation_number: '', title: '', venue: '', published_at: '' });
      load();
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <section>
      <h1>成果引用登记册</h1>
      <p className="hint">登记册公开可查，记录每项成果实际使用的数据版本；授权撤回或到期后登记仍然保留。</p>

      <table>
        <thead><tr><th>引用编号</th><th>成果</th><th>数据集</th><th>使用版本</th><th>登记人</th></tr></thead>
        <tbody>
          {citations.map((c) => (
            <tr key={c.id}>
              <td>{c.citation_number}</td>
              <td>{c.title}{c.venue ? `（${c.venue}）` : ''}</td>
              <td>{c.dataset?.title}</td>
              <td>v{c.version?.version}</td>
              <td>{c.user?.name}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {user && (
        <>
          <h2>登记新成果</h2>
          <form onSubmit={submit} className="narrow">
            <label>所用数据授权（版本以获批时为准）
              <select value={form.grant_id} onChange={(e) => setForm({ ...form, grant_id: e.target.value })} required>
                <option value="">请选择</option>
                {grants.map((g) => (
                  <option key={g.id} value={g.id}>{g.dataset?.title} · v{g.version?.version}</option>
                ))}
              </select>
            </label>
            <label>引用编号（DOI / 报告号）
              <input value={form.citation_number} onChange={(e) => setForm({ ...form, citation_number: e.target.value })} required />
            </label>
            <label>成果标题
              <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
            </label>
            <label>发表渠道
              <input value={form.venue} onChange={(e) => setForm({ ...form, venue: e.target.value })} />
            </label>
            <label>发表日期
              <input type="date" value={form.published_at} onChange={(e) => setForm({ ...form, published_at: e.target.value })} />
            </label>
            {error && <p className="error">{error}</p>}
            {ok && <p className="ok">{ok}</p>}
            <button type="submit">登记</button>
          </form>
        </>
      )}
    </section>
  );
}
