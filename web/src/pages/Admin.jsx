import React, { useEffect, useState } from 'react';
import { api } from '../api';

export default function Admin({ user }) {
  const [applications, setApplications] = useState([]);
  const [datasets, setDatasets] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [versionForms, setVersionForms] = useState({});
  const [newDataset, setNewDataset] = useState(null);

  const load = () => {
    if (user?.role !== 'approver') return;
    api('/admin/applications').then((d) => setApplications(d.applications)).catch((e) => setError(e.message));
    api('/admin/datasets').then((d) => setDatasets(d.datasets)).catch((e) => setError(e.message));
  };
  useEffect(() => { load(); }, [user]);

  if (user?.role !== 'approver') return <p className="error">需要审批人账号登录。</p>;

  const decide = async (app, action) => {
    setError(''); setMessage('');
    const note = window.prompt(action === 'approve' ? '批准备注（可空）' : '拒绝原因');
    if (action === 'reject' && note === null) return;
    try {
      await api(`/admin/applications/${app.id}/${action}`, { method: 'POST', body: { note } });
      setMessage(`申请 #${app.id} 已${action === 'approve' ? '批准' : '拒绝'}（邮件通知已写入日志）`);
      load();
    } catch (e) { setError(e.message); }
  };

  const revoke = async (grant) => {
    if (!window.confirm(`确认撤回 ${grant.user?.name} 对数据集 #${grant.dataset_id} 的授权？下载链接将立即失效。`)) return;
    try {
      await api(`/admin/grants/${grant.id}/revoke`, { method: 'POST' });
      setMessage(`授权 #${grant.id} 已撤回`);
      load();
    } catch (e) { setError(e.message); }
  };

  const addVersion = async (dataset) => {
    const form = versionForms[dataset.id] || {};
    try {
      await api(`/admin/datasets/${dataset.id}/versions`, {
        method: 'POST',
        body: { csv: form.csv, changelog: form.changelog },
      });
      setMessage(`数据集「${dataset.title}」已发布新版本`);
      setVersionForms((cur) => ({ ...cur, [dataset.id]: {} }));
      load();
    } catch (e) { setError(e.message); }
  };

  const createDataset = async () => {
    try {
      const fields = newDataset.fields.split('\n').filter(Boolean).map((line) => {
        const [name, label, restricted] = line.split(',').map((s) => s.trim());
        return { name, label: label || name, is_restricted: restricted === '受限' || restricted === '1' };
      });
      await api('/admin/datasets', {
        method: 'POST',
        body: { title: newDataset.title, description: newDataset.description, department: newDataset.department, fields, csv: newDataset.csv },
      });
      setMessage('数据集已创建');
      setNewDataset(null);
      load();
    } catch (e) { setError(e.message); }
  };

  const pending = applications.filter((a) => a.status === 'pending');

  return (
    <section>
      <h1>审批管理</h1>
      {error && <p className="error">{error}</p>}
      {message && <p className="ok">{message}</p>}

      <h2>待审批申请（{pending.length}）</h2>
      <table>
        <thead><tr><th>申请人</th><th>数据集</th><th>版本</th><th>字段</th><th>期限</th><th>用途</th><th>操作</th></tr></thead>
        <tbody>
          {pending.map((a) => (
            <tr key={a.id}>
              <td>{a.user?.name}</td>
              <td>{a.dataset?.title}</td>
              <td>v{a.version?.version}</td>
              <td>{a.fields.join(', ')}</td>
              <td>{a.access_start?.slice(0, 10)} ~ {a.access_end?.slice(0, 10)}</td>
              <td className="purpose">{a.purpose}</td>
              <td>
                <button className="small" onClick={() => decide(a, 'approve')}>批准</button>{' '}
                <button className="small danger" onClick={() => decide(a, 'reject')}>拒绝</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <h2>数据集与授权</h2>
      {datasets.map((d) => (
        <div key={d.id} className="card">
          <h3>{d.title}（版本: {d.versions.map((v) => `v${v.version}`).join(', ')}）</h3>
          <p className="meta">字段: {d.fields.map((f) => `${f.name}${f.is_restricted ? '(受限)' : ''}`).join(', ')}</p>

          {d.grants.length > 0 && (
            <table>
              <thead><tr><th>被授权人</th><th>版本</th><th>字段</th><th>有效期至</th><th>状态</th><th></th></tr></thead>
              <tbody>
                {d.grants.map((g) => (
                  <tr key={g.id}>
                    <td>{g.user?.name}</td>
                    <td>v{d.versions.find((v) => v.id === g.dataset_version_id)?.version}</td>
                    <td>{g.fields.join(', ')}</td>
                    <td>{g.valid_until?.slice(0, 10)}</td>
                    <td>{g.status === 'active' ? '有效' : '已撤回'}</td>
                    <td>{g.status === 'active' && <button className="small danger" onClick={() => revoke(g)}>撤回</button>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          <details>
            <summary>发布新版本</summary>
            <label>变更说明
              <input
                value={versionForms[d.id]?.changelog || ''}
                onChange={(e) => setVersionForms((cur) => ({ ...cur, [d.id]: { ...cur[d.id], changelog: e.target.value } }))}
              />
            </label>
            <label>CSV 内容（首行为表头）
              <textarea
                rows={5}
                value={versionForms[d.id]?.csv || ''}
                onChange={(e) => setVersionForms((cur) => ({ ...cur, [d.id]: { ...cur[d.id], csv: e.target.value } }))}
              />
            </label>
            <button className="small" onClick={() => addVersion(d)}>发布 v{d.versions.length + 1}</button>
          </details>
        </div>
      ))}

      <h2>新建数据集</h2>
      {newDataset ? (
        <div className="card narrow">
          <label>标题<input value={newDataset.title} onChange={(e) => setNewDataset({ ...newDataset, title: e.target.value })} /></label>
          <label>描述<input value={newDataset.description} onChange={(e) => setNewDataset({ ...newDataset, description: e.target.value })} /></label>
          <label>部门<input value={newDataset.department} onChange={(e) => setNewDataset({ ...newDataset, department: e.target.value })} /></label>
          <label>字段（每行一个：name,label,受限 标记）
            <textarea rows={4} value={newDataset.fields} onChange={(e) => setNewDataset({ ...newDataset, fields: e.target.value })} />
          </label>
          <label>首版 CSV
            <textarea rows={5} value={newDataset.csv} onChange={(e) => setNewDataset({ ...newDataset, csv: e.target.value })} />
          </label>
          <button onClick={createDataset}>创建</button>{' '}
          <button onClick={() => setNewDataset(null)}>取消</button>
        </div>
      ) : (
        <button onClick={() => setNewDataset({ title: '', description: '', department: '', fields: '', csv: '' })}>新建数据集</button>
      )}
    </section>
  );
}
