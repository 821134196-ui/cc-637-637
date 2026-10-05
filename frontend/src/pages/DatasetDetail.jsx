import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'

export default function DatasetDetail() {
  const { id } = useParams()
  const { user } = useAuth()
  const [dataset, setDataset] = useState(null)
  const [preview, setPreview] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get(`/api/datasets/${id}`).then(setDataset).catch((e) => setError(e.message))
    api.get(`/api/datasets/${id}/preview`).then(setPreview).catch(() => setPreview(null))
  }, [id])

  if (error) return <p className="error">{error}</p>
  if (!dataset) return <p>加载中…</p>

  const latest = dataset.versions[0]

  return (
    <>
      <h1>{dataset.name}</h1>
      <p className="muted">{dataset.description}</p>

      <div className="card">
        <h2>数据预览（仅公开字段，前 5 行）</h2>
        {preview && preview.columns.length > 0 ? (
          <>
            <table className="preview-table">
              <thead>
                <tr>{preview.columns.map((c) => <th key={c}>{c}</th>)}</tr>
              </thead>
              <tbody>
                {preview.rows.map((row, i) => (
                  <tr key={i}>{row.map((cell, j) => <td key={j}>{cell}</td>)}</tr>
                ))}
              </tbody>
            </table>
            <p className="preview-note">
              版本 v{preview.version} · 受限字段不会出现在预览中
              {latest?.restricted_field_count > 0 && `（本数据集另有 ${latest.restricted_field_count} 个受限字段，需申请获批后使用）`}
            </p>
          </>
        ) : <p className="muted">暂无可预览数据</p>}
      </div>

      <div className="card">
        <h2>版本列表</h2>
        <table>
          <thead>
            <tr><th>版本</th><th>公开字段</th><th>受限字段</th><th>说明</th><th>发布时间</th></tr>
          </thead>
          <tbody>
            {dataset.versions.map((v) => (
              <tr key={v.id}>
                <td>v{v.version}</td>
                <td>{v.public_fields.map((f) => f.label || f.name).join('、') || '—'}</td>
                <td>{v.restricted_field_count > 0 ? `${v.restricted_field_count} 个（需申请）` : '无'}</td>
                <td className="muted">{v.changelog || '—'}</td>
                <td className="muted">{new Date(v.created_at).toLocaleDateString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="actions">
          {user
            ? <Link to={`/apply/${dataset.id}`}><button className="primary">申请使用此数据集</button></Link>
            : <span className="muted">登录后可提交访问申请</span>}
        </div>
      </div>
    </>
  )
}
