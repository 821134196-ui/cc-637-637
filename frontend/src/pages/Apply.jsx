import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api'

export default function Apply() {
  const { datasetId } = useParams()
  const navigate = useNavigate()
  const [dataset, setDataset] = useState(null)
  const [versionId, setVersionId] = useState(null)
  const [schema, setSchema] = useState(null)
  const [selected, setSelected] = useState([])
  const [purpose, setPurpose] = useState('')
  const [periodStart, setPeriodStart] = useState('')
  const [periodEnd, setPeriodEnd] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get(`/api/datasets/${datasetId}`).then((d) => {
      setDataset(d)
      if (d.versions.length > 0) setVersionId(d.versions[0].id)
    }).catch((e) => setError(e.message))
  }, [datasetId])

  useEffect(() => {
    if (!versionId) return
    setSchema(null)
    setSelected([])
    api.get(`/api/versions/${versionId}/schema`).then(setSchema).catch((e) => setError(e.message))
  }, [versionId])

  const fields = useMemo(() => schema?.fields ?? [], [schema])

  function toggle(name) {
    setSelected((cur) => cur.includes(name) ? cur.filter((f) => f !== name) : [...cur, name])
  }

  async function submit(e) {
    e.preventDefault()
    setError('')
    if (selected.length === 0) { setError('请至少选择一个字段'); return }
    setBusy(true)
    try {
      await api.post('/api/requests', {
        dataset_version_id: versionId,
        purpose,
        requested_fields: selected,
        period_start: periodStart,
        period_end: periodEnd,
      })
      navigate('/requests')
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (!dataset) return <p>{error || '加载中…'}</p>

  return (
    <>
      <h1>申请访问：{dataset.name}</h1>
      <form className="card" onSubmit={submit}>
        <label>数据集版本（申请获批后仅可下载所选版本）</label>
        <select value={versionId ?? ''} onChange={(e) => setVersionId(Number(e.target.value))}>
          {dataset.versions.map((v) => (
            <option key={v.id} value={v.id}>v{v.version}{v.changelog ? ` — ${v.changelog}` : ''}</option>
          ))}
        </select>

        <h3>申请字段</h3>
        <p className="muted">受限字段将在获批后随数据一并开放；未获批字段不会出现在下载结果中。</p>
        {schema ? fields.map((f) => (
          <div className="field-row" key={f.name}>
            <input type="checkbox" id={`f-${f.name}`} checked={selected.includes(f.name)} onChange={() => toggle(f.name)} />
            <label htmlFor={`f-${f.name}`} style={{ margin: 0, color: 'var(--text)' }}>
              {f.label || f.name} <span className="muted">({f.name})</span>
            </label>
            <span className={`badge ${f.is_restricted ? 'restricted' : 'public'}`}>
              {f.is_restricted ? '受限' : '公开'}
            </span>
            {f.description && <span className="muted">— {f.description}</span>}
          </div>
        )) : <p className="muted">加载字段中…</p>}

        <label>使用用途（不少于 10 字）</label>
        <textarea rows="3" value={purpose} onChange={(e) => setPurpose(e.target.value)}
          placeholder="请说明研究目的、数据使用方式与预期成果" required minLength={10} />

        <div className="grid">
          <div>
            <label>使用期限开始</label>
            <input type="date" value={periodStart} onChange={(e) => setPeriodStart(e.target.value)} required />
          </div>
          <div>
            <label>使用期限结束</label>
            <input type="date" value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} required />
          </div>
        </div>

        {error && <p className="error">{error}</p>}
        <div className="actions">
          <button className="primary" disabled={busy}>{busy ? '提交中…' : '提交申请'}</button>
        </div>
      </form>
    </>
  )
}
