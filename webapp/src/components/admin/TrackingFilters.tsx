import React from 'react';
import { Search } from 'lucide-react';

interface TrackingFiltersProps {
    search: string;
    setSearch: (s: string) => void;
    activeTab: string;
    setActiveTab: (t: string) => void;
    tabs: { id: string; label: string; count?: number }[];
}

const TrackingFilters: React.FC<TrackingFiltersProps> = ({ search, setSearch, activeTab, setActiveTab, tabs }) => {
    return (
        <div className="tracking-filters">
            <style>{`
                .tracking-filters {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    margin-bottom: 20px;
                }
                .search-bar {
                    position: relative;
                    width: 100%;
                }
                .search-bar input {
                    width: 100%;
                    padding: 8px 12px 8px 36px;
                    background: var(--bg-glass);
                    border: 1px solid var(--border-glass);
                    border-radius: 8px;
                    color: var(--text-primary);
                    font-size: 0.9rem;
                    outline: none;
                    transition: border-color 0.2s;
                }
                .search-bar input:focus {
                    border-color: var(--accent-primary);
                }
                .search-icon {
                    position: absolute;
                    left: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--text-secondary);
                    pointer-events: none;
                }
                .tabs-container {
                    display: flex;
                    gap: 4px;
                    background: rgba(255,255,255,0.03);
                    padding: 4px;
                    border-radius: 10px;
                    width: fit-content;
                }
                .filter-tab {
                    padding: 6px 12px;
                    border-radius: 6px;
                    border: none;
                    background: transparent;
                    color: var(--text-secondary);
                    font-size: 0.85rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }
                .filter-tab:hover {
                    color: var(--text-primary);
                    background: rgba(255,255,255,0.05);
                }
                .filter-tab.active {
                    background: var(--accent-primary);
                    color: #fff;
                }
                .tab-count {
                    background: rgba(0,0,0,0.2);
                    padding: 1px 6px;
                    border-radius: 10px;
                    font-size: 0.7rem;
                }
            `}</style>

            <div className="search-bar">
                <Search className="search-icon" size={16} />
                <input
                    type="text"
                    placeholder="Filtrar por código, cidade ou status..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            <div className="tabs-container">
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        className={`filter-tab ${activeTab === tab.id ? 'active' : ''}`}
                        onClick={() => setActiveTab(tab.id)}
                    >
                        {tab.label}
                        {tab.count !== undefined && <span className="tab-count">{tab.count}</span>}
                    </button>
                ))}
            </div>
        </div>
    );
};

export default TrackingFilters;
