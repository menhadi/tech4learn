type Item = { key: string; label: string; requires?: string[] };
const display = (value: string) =>
  value.replace(/_/g, " ").replace(/^./, (c) => c.toUpperCase());

export function PermissionMatrix({
  catalogue,
  selected,
  allowed,
  onChange,
}: {
  catalogue: Item[];
  selected: string[];
  allowed?: string[];
  onChange?: (keys: string[]) => void;
}) {
  const modules = [...new Set(catalogue.map((p) => p.key.split(".")[0]))];
  const priority = ["view", "create", "edit", "archive", "delete"];
  const actions = [
    ...new Set(catalogue.map((p) => p.key.split(".").slice(1).join("."))),
  ];
  actions.sort(
    (a, b) =>
      (priority.includes(a) ? priority.indexOf(a) : 99) -
        (priority.includes(b) ? priority.indexOf(b) : 99) || a.localeCompare(b),
  );
  function change(key: string, checked: boolean) {
    const next = new Set(selected);
    if (checked) {
      const add = (value: string) => {
        if (next.has(value)) return;
        next.add(value);
        for (const dependency of catalogue.find((p) => p.key === value)
          ?.requires || [])
          add(dependency);
      };
      add(key);
    } else {
      next.delete(key);
      if (key.endsWith(".view"))
        for (const p of catalogue)
          if (p.key.startsWith(key.split(".")[0] + ".")) next.delete(p.key);
      let changed = true;
      while (changed) {
        changed = false;
        for (const p of catalogue)
          if (next.has(p.key) && p.requires?.some((k) => !next.has(k))) {
            next.delete(p.key);
            changed = true;
          }
      }
    }
    onChange?.([...next]);
  }
  return (
    <div
      className="permission-table-scroll"
      tabIndex={0}
      aria-label="Module permission table; scroll horizontally for more actions"
    >
      <table className="permission-table">
        <caption>Permissions by module and action</caption>
        <thead>
          <tr>
            <th scope="col">Module</th>
            {actions.map((a) => (
              <th scope="col" key={a}>
                {display(a)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {modules.map((m) => (
            <tr key={m}>
              <th scope="row">{display(m)}</th>
              {actions.map((a) => {
                const item = catalogue.find((p) => p.key === `${m}.${a}`);
                return (
                  <td key={a}>
                    {item ? (
                      <label title={item.label}>
                        <input
                          type="checkbox"
                          aria-label={item.label}
                          checked={selected.includes(item.key)}
                          disabled={
                            !onChange ||
                            item.key === "organisation.view" ||
                            (!!allowed &&
                              (!allowed.includes(item.key) ||
                                item.requires?.some(
                                  (k) => !allowed.includes(k),
                                )))
                          }
                          onChange={(e) => change(item.key, e.target.checked)}
                        />
                        <span className="sr-only">{item.label}</span>
                      </label>
                    ) : (
                      <span aria-label="Not available">—</span>
                    )}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
