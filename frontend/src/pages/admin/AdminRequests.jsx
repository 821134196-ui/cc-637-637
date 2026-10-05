import { useEffect, useState } from 'react'
import { api } from '../../api'

const STATUS_TEXT = { pending: '待审批', approved: '已批准', rejected: '已拒绝' }

export default function AdminRequests() {
  const [requests, setRequests] = useState([])
  const [filter, setFilter] = useState('pending')
  const [narrow, setNarrow] = useState({})
  const [error, setError] = useState('')

  function refresh() {
    const qs = filter ? `?status=${filter}` : ''
    api.get(`/api/admin/requests${qs}`).then(setRequests).catch((e) => setError(e.message))
  }

  useEffect(refresh, [filter])

  function toggleNarrow(requestId, field, requested) {
    setNarrow((cur) => {
      const current = cur[requestId] ?? [...requested]
      const next = current.includes(field) ? current.filter((f) => f !== field) : [...current, field]
      return { ...cur, [requestId]: next }
    })
  }

  async function approve(r) {
    setError('')
    const fields = narrow[r.id] ?? r.requested_fields
    if (fields.length === 0) { setError(`申请 #${r.id}：至少保留一个批准字段`); return }
    try {
      await api.post(`/api/admin/requests/${r.id}/approve`, { granted_fields: fields })
      refresh()
    } catch (e) { setError(e.message) }
  }

  async function reject(r) {
    const reason = window.prompt(`请输入拒绝申请 #${r.id} 的原因：`)
    if (!reason) return
    setError('')
    try {
      await api.post(`/api/admin/requests/${r.id}/reject`, { reason })
      refresh()
    } catch (e) { setError(e.message) }
  }

  return (
    <>
      <h1>申请审批</h1>
      <p>
        筛选：
        <select value={filter} onChange={(e) => setFilter(e.target.value)} style={{ width: 'auto', marginLeft: 8 }}>
          <option value="pending">待审批</option>
          <option value="approved">已批准</option>
          <option value="rejected">已拒绝</option>
          <option value="">全部</option>
        </select>
      </p>
      {error && <p className="error">{error}</p>}
      {requests.length === 0 && <p className="muted">暂无记录。</p>}
      {requests.map((r) => (
        <div className="card" key={r.id}>
          <h3>
            申请 #{r.id}：{r.dataset.name} v{r.version.version}{' '}
            <span className={`badge ${r.status}`}>{STATUS_TEXT[r.status]}</span>
          </h3>
          <p className="muted">申请人：{r.applicant.name}（{r.applicant.email}） · 期限：{r.period_start} 至 {r.period_end}</p>
          <p>用途：{r.purpose}</p>
          {r.status === 'pending' ? (
            <>
              <h3>批准字段（可收窄，不可超出申请范围）</h3>
              {r.requested_fields.map((f) => {
                const checked = (narrow[r.id] ?? r.requested_fields).includes(f)
                return (
                  <div className="field-row" key={f}>
                    <input type="checkbox" id={`r${r.id}-${f}`} checked={checked} onChange={() => toggleNarrow(r.id, f, r.requested_fields)} />
                    <label htmlFor={`r${r.id}-${f}`} style={{ margin: 0, color: 'var(--text)' }}>{f}</label>
                  </div>
                )
              })}
              <div className="actions">
                <button className="primary" onClick={() => approve(r)}>批准</button>
                <button className="danger" onClick={() => reject(r)}>拒绝</button>
              </div>
            </>
          ) : (
            <p className="muted">
              申请字段：{r.requested_fields.join('、')}
              {r.decision_reason && ` · 处理意见：${r.decision_reason}`}
            </p>
          )}
        </div>
      ))}
    </>
  )
}
