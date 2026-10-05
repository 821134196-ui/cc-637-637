import React, { useEffect, useState } from 'react';
import { api } from '../api';

export default function MyGrants() {
  const [grants, setGrants] = useState([]);
  const [links, setLinks] = useState({});
  const [error, setError] = useState('');

  const load = () => api('/grants').then((d) => setGrants(d.grants)).catch((e) => setError(e.message));
  useEffect(() => { load(); }, []);

  const issueLink = async (grant) => {
    setError('');
    try {
      const d = await api(`/grants/${grant.id}/token`, { method: 'POST' });
      setLinks((cur) => ({ ...cur, [grant.id]: d.url }));
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <section>
      <h1>已获批范围与可用下载</h1>
      <p className="hint">下载内容严格限于获批版本与获批字段；授权撤回或到期后，已生成的链接立即失效。</p>
      {error && <p className="error">{error}</p>}
      {grants.map((g) => (
        <div key={g.id} className="card">
          <h2>{g.dataset?.title} · v{g.version?.version}</h2>
          <p className="meta">获批字段: {g.fields.join(', ')}</p>
          <p className="meta">
            有效期: {g.valid_from?.slice(0, 10)} ~ {g.valid_until?.slice(0, 10)} ·
            状态: {g.status === 'active' ? (g.usable ? '有效' : '未在有效期内') : '已撤回'}
          </p>
          {g.usable ? (
            links[g.id] ? (
              <p>
                <a href={links[g.id]} download>下载 CSV</a>
                <span className="meta">（链接: {links[g.id]}）</span>
              </p>
            ) : (
              <button onClick={() => issueLink(g)}>生成下载链接</button>
            )
          ) : (
            <p className="hint">该授权不可用，无法生成下载链接。</p>
          )}
        </div>
      ))}
      {!grants.length && <p className="hint">暂无获批授权。</p>}
    </section>
  );
}
