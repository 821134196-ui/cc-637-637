import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'

const STATUS_TEXT = { pending: '待审批', approved: '已批准', rejected: '已拒绝' }

export default function MyRequests() {
  const [requests, setRequests] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/api/requests').then(setRequests).catch((e) => setError(e.message))
  }, [])

  return (
    <>
      <h1>我的申请</h1>
      {error && <p className="error">{error}</p>}
      {requests.length === 0 && !error && (
        <div className="card"><p className="muted">还没有申请记录，去 <Link to="/">公开目录</Link> 选择数据集提交申请。</p></div>
      )}
      {requests.map((r) => (
        <div className="card" key={r.id}>
          <h3>
            申请 #{r.id}：{r.dataset.name} v{r.version.version}{' '}
            <span className={`badge ${r.status}`}>{STATUS_TEXT[r.status]}</span>
          </h3>
          <p className="muted">用途：{r.purpose}</p>
          <p>
            申请字段：{r.requested_fields.map((f) => <span key={f} className="badge public" style={{ marginRight: 6 }}>{f}</span>)}
          </p>
          <p className="muted">使用期限：{r.period_start} 至 {r.period_end} · 提交于 {new Date(r.created_at).toLocaleString()}</p>
          {r.status === 'rejected' && (
            <>
              <p className="error">拒绝原因：{r.decision_reason}</p>
              <p className="muted">如需变更用途，请前往数据集详情页重新提交一份新申请。</p>
            </>
          )}
          {r.status === 'approved' && r.grant_id && (
            <p className="success">已开通下载，前往 <Link to="/downloads">我的下载</Link> 获取数据。</p>
          )}
        </div>
      ))}
    </>
  )
}
