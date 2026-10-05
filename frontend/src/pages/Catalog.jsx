import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'

export default function Catalog() {
  const [datasets, setDatasets] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/api/datasets').then(setDatasets).catch((e) => setError(e.message))
  }, [])

  return (
    <>
      <h1>公开数据目录</h1>
      <p className="muted">以下数据集面向全校公开浏览。预览仅含公开字段；受限字段需提交访问申请并获批后方可下载。</p>
      {error && <p className="error">{error}</p>}
      {datasets.map((d) => (
        <div className="card" key={d.id}>
          <h3><Link to={`/datasets/${d.id}`}>{d.name}</Link> <span className="muted">v{d.latest_version?.version}</span></h3>
          <p className="muted">{d.description}</p>
          <p>
            {d.public_fields.map((f) => <span key={f.name} className="badge public" style={{ marginRight: 6 }}>{f.label || f.name}</span>)}
            {d.restricted_field_count > 0 && (
              <span className="badge restricted">{d.restricted_field_count} 个受限字段需申请</span>
            )}
          </p>
        </div>
      ))}
      {datasets.length === 0 && !error && <p className="muted">暂无数据集</p>}
    </>
  )
}
