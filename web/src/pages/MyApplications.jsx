import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

const STATUS_LABEL = { pending: '待审批', approved: '已批准', rejected: '已拒绝' };

export default function MyApplications() {
  const [applications, setApplications] = useState([]);
  const [error, setError] = useState('');

  const load = () => api('/applications').then((d) => setApplications(d.applications)).catch((e) => setError(e.message));
  useEffect(() => { load(); }, []);

  return (
    <section>
      <h1>我的申请</h1>
      {error && <p className="error">{error}</p>}
      <table>
        <thead>
          <tr><th>数据集</th><th>版本</th><th>字段</th><th>期限</th><th>用途</th><th>状态</th><th></th></tr>
        </thead>
        <tbody>
          {applications.map((a) => (
            <tr key={a.id}>
              <td>{a.dataset?.title}</td>
              <td>v{a.version?.version}</td>
              <td>{a.fields.join(', ')}</td>
              <td>{a.access_start?.slice(0, 10)} ~ {a.access_end?.slice(0, 10)}</td>
              <td className="purpose">{a.purpose}</td>
              <td><span className={`status ${a.status}`}>{STATUS_LABEL[a.status]}</span>
                {a.decision_note && <div className="meta">{a.decision_note}</div>}
              </td>
              <td>
                {a.status === 'rejected' && (
                  <Link className="button small" to={`/apply/${a.dataset_id}`}>改变用途？重新申请</Link>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {!applications.length && <p className="hint">还没有申请记录。</p>}
    </section>
  );
}
