vals={'Medicaid':196231000000,'Out_of_pocket':23147000000,'Other_payers':64305000000,'Total_HCBS_LTSS':283683000000} 
for k,v in vals.items(): 
    print(k,round(v/1e9,1)) 
