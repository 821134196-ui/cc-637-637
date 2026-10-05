import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function Catalog() {
  const [datasets, setDatasets] = useState([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api('/catalog').then((d) => setDatasets(d.datasets)).catch((e) => setError(e.message));
  }, []);

  return (
    <section>
      <h1>公开数据集目录</h1>
      <p className="hint">此处仅展示公开字段。受限字段需提交访问申请并获批后方可下载。</p>
      {error && <p className="error">{error}</p>}
      <div className="cards">
        {datasets.map((d) => (
          <div key={d.id} className="card">
            <h2><Link to={`/datasets/${d.id}`}>{d.title}</Link></h2>
            <p>{d.description}</p>
            <p className="meta">{d.department} · 最新版本 v{d.latest_version}</p>
            <p className="meta">公开字段: {d.public_fields.map((f) => f.label || f.name).join('、')}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
