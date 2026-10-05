import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../api';

export default function Apply({ user }) {
  const { datasetId } = useParams();
  const navigate = useNavigate();
  const [meta, setMeta] = useState(null);
  const [versionId, setVersionId] = useState('');
  const [selected, setSelected] = useState([]);
  const [purpose, setPurpose] = useState('');
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (!user) { navigate('/login'); return; }
    api(`/datasets/${datasetId}/fields`).then((d) => {
      setMeta(d);
      if (d.versions.length) setVersionId(String(d.versions[0].id));
    }).catch((e) => setError(e.message));
  }, [datasetId]);

  const toggle = (name) => {
    setSelected((cur) => cur.includes(name) ? cur.filter((f) => f !== name) : [...cur, name]);
  };

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      await api('/applications', {
        method: 'POST',
        body: {
          dataset_id: Number(datasetId),
          dataset_version_id: Number(versionId),
          purpose,
          fields: selected,
          access_start: start,
          access_end: end,
        },
      });
      setDone(true);
    } catch (err) {
      setError(err.message);
    }
  };

  if (!meta) return <p>加载中…</p>;

  if (done) {
    return (
      <section className="narrow">
        <h1>申请已提交</h1>
        <p>审批结果将通过邮件通知（模拟，写入后端日志）。</p>
        <button onClick={() => navigate('/applications')}>查看我的申请</button>
      </section>
    );
  }

  return (
    <section className="narrow">
      <h1>提交访问申请</h1>
      <form onSubmit={submit}>
        <label>数据版本
          <select value={versionId} onChange={(e) => setVersionId(e.target.value)}>
            {meta.versions.map((v) => (
              <option key={v.id} value={v.id}>v{v.version}（{v.changelog || '—'}）</option>
            ))}
          </select>
        </label>

        <fieldset>
          <legend>申请字段范围</legend>
          {meta.fields.map((f) => (
            <label key={f.name} className="checkbox">
              <input
                type="checkbox"
                checked={selected.includes(f.name)}
                onChange={() => toggle(f.name)}
              />
              {f.label || f.name}
              {f.is_restricted && <span className="tag">受限</span>}
            </label>
          ))}
        </fieldset>

        <label>使用期限（起）
          <input type="date" value={start} onChange={(e) => setStart(e.target.value)} required />
        </label>
        <label>使用期限（止）
          <input type="date" value={end} onChange={(e) => setEnd(e.target.value)} required />
        </label>

        <label>用途说明（不少于 10 字）
          <textarea value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={4} required minLength={10} />
        </label>

        {error && <p className="error">{error}</p>}
        <button type="submit" disabled={!selected.length}>提交申请</button>
      </form>
    </section>
  );
}
