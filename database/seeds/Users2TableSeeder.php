<?php

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Address;

class Users2TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $name = ["คุณสัญชัย แซ่ตั้ง","คุณนคร ขยันดี","คุณศราวุฒิ ศรีวัง","คุณทศพร ปุณิการณ์","คุณพรพิมล นามพิทักษ์","คุณวิภาดา คำมงคล","คุณอดิศักดิ์ คำมุกชิก","คุณสุรยุทธ เอียยะบุตร","คุณวรภร รายน้ำเงิน","คุณสมพงษ์ น้อยเสนา","คุณวิศรตกรณ์ แก้วเกิด","คุณเบ็ญจาย์ รักษา","คุณชโยธิศ ราษฎร์วิรุฬห์กิจ","คุณไพลิน แดนสันเทียะ","คุณเจริญ ภาควิวรรธน์","คุณวิทย์สุณี โกสุม","คุณมนู สันธนะพานิช","คุณชัยยศ ยิ้มมี","คุณสุรีรัตน์ หวลประเสริฐ","คุณธนัช แก้วอำภัย","คุณพีระพัฒน์ คล้ายเพ็ญ","คุณรณชัย ชัยประสิทธิกุล","คุณชัยรัตน์ ลือพงศ์พัฒนะ","คุณเอกภพ อิสราภิวัฒน์","คุณณัฐมณี สอดโคกสูง","คุณเนตรนภา จิตติวัฒนา","คุณเมทินี คงสูงเนิน","คุณทวัญญา ตีระวัฒนะพงษ์","คุณจันทร์จิรา อยู่ทองหลาง","คุณปภังกร ภู่มาก","คุณนัยนา พรหมดิเรก","คุณชัยชนะ เถาว์กลาง","คุณชลิดา อำนาจพิทักษ์","คุณนิรันทร์ บัวอินทร์","คุณลักขณา  พูนสุขสันต์","กิตติพร นามโย","คุณธนาชัย","คุณรัญชวน ทวีพันธ์","คุณจุ่ม ขำมอ","คุณสุรีรัตน์ มั่นเหมาะ","สันติพงศ์  ศรีกันทา","อรกัญญา  วงค์พลัง","คุณอภิวัฒน์  ชาววิไล","ภพ  หมอศารตร์","มิ้ว","แพรพิศุทธิ์  ศรีหนุ้น","สมบูรณ์  แสงสว่างวัฒนะ","คุณสมศรี  กันชาหล้า","จักรพันธ์ คำอ่อน","บุรินทรน์พัฒน์ ศรีชัยกุล","นริสรา  ใบงิ้ว","เปรมพิมล กันทะนะ","วัชราภรณ์ ก้อนใจ","อารีย์ แก้วแปงเขียว","พีรดนย์  วิชิตพันธุ์","ธัญญธร ขันแก้วหล้า","ณัฏฐ์ภัทร์ รุ่งตระกูลธรรม","สาวิตรี  หวันแก้ว","คุณปฏิพัตกรณ์","คุณศิริจันญา กุลรัตน์","จินตนา คำมะนาม","คุณนฤเบศ เบเยียกู่","คุณธนัฏฐ์พงษ์ ธนชัยภัทร์","คุณเมธิยา สิงคะ","คุณศศิกานต์ ม่วงมุข","คุณประกิจ บุญมา","คุณศิริประภา ดีพรม","คุณศิริฉัตร ใจหวาน","คุณ อิทธิฤทธิ์ อินทรีย์จ่าง","คุณภัทราภรณ์ ธุวะคำ","คุณอนุรัก วงค์เลย","คุณ ณัฐธาพร ยอดปัญญา","คณอาทิตยา พงษ์สุริยา","คุณณิชมน คหบดีกนกกุล","คุณพัณณ์ภัสสร คำศิริ","คุณศรันย์ ปันแปง","คุณ พงศกร หุ้มทอง","คุณ เสาวนีย์ จันทร์สว่าง","คุณณัฐรินทร์ สมบัติ","คุณวุฒิไกร  วระวัฒน์","คุณวิชัย  บุญศิริ","คุณยุพยงค์  เอกอุเวชกุล","คุณพรณิภา  บัวใหญ่รักษา","คุณธีรยุทธ  ธัญชนัชพรกุล","คุณยุวลี  ศรีบุปผา","คุณธีรุธ  กุฏโสม","คุณพัลลภ  วิจบ","คุณสายันต์  วงษ์โก","นายธนา  ปัญญาแก้ว","คุณวันวิสา  ฤกษ์กลาง","คุณรัชมงคล  ประทุมศาลา","คุณปรียาภรณ์  พรหมภักดี","คุณภัทรนุช เหลืองวจรพิบูล","นิติ  สิทธิคาถา","น้องไนซ์หมูกะทะ","ธรรมรัตน์  บุญเจือ","คุณอาป๋าสาขา","ศุภกิจ  อุทกัง","ศริพร","วรินทร์พรรณ์ ทองอนันต์","ศุกภสวัสดิ์  รัธรรมา","ฤทัยรัตน์ สายสำราญ","คุณ ชยุต คลังดงเค็ง","คุณ ดวงรัตน์ อาจสาคร","ประคองศรี ฟักกัด","อรอุมา ตันยะกุล","กัณฑ์เอนก ศักดิ์ศรีเจริญ"];
        $phone =["087-976-3088","089-033-6482","085-458-6857","080-133-4069","081-056-6561","092-632-9782","084-686-0503","081-471-5432","065-829-5715","089-710-5661","087-865-7445","087-450-5444","089-666-1736","063-621-2454","094-291-7952","095-253-5310","091-078-2080","080-799-9932","099-471-6688","098-226-6242","086-023-9052","081-725-5330","063-969-5465","089-624-5992","098-942-8596","062-518-8855","088-371-7108","089-444-6499","093-665-5547","080-155-9540","081-879-8816","098-606-5287","096-941-2994","061-351-1087","086-383-5006","082-691-2486","099-242-5335","086-185-6188","099-242-5335","086-918-4881","082-620-2104","082-446-6458","085-029-8766","082-994-9149","064-162-2699","099-269-1469","083-023-0579","089-554-6808","068-995-4336","081-707-1401","068-995-4336","083-516-5771","085-347-1383","081-885-6874","085-523-0044","087-193-5392","087-193-5392","082-682-8003","062-267-7712","098-394-1716","098-746-3532","062-319-8200","095-246-4594","098-854-9196","093-182-1818","061-278-5113","099-855-2933","086-619-9596","086-188-8708","099-189-8891","062-023-8787","095-760-2917","062-249-8859","080-676-7387","086-382-8158","088-090-0567","095-448-5951","091-859-7129","095-448-9974","082-006-7859","085-852-3585","086-855-9972","089-6222318","091-8655-588","091-064-6494","097-031-8726","089-149-4643","085-853-2006","089-002-6529","093-549-2663","085-274-5444","087-774-7842","093-695-2999","084-781-5229","080-821-8793","082-454-6751","085-306-7870","098-248-0063","081-863-3221","082-552-8886","095-701-9082","085-222-3056","061-896-6945","084-033-9826","083-589-0843","086-626-2223","089-394-6651"];
        $zipcode = ["41000","41000","41000","41000","41000","41000","41000","41000","41000","41000","41000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","30000","50000","50000","50000","50000","50200","50000","50200","50000","50130","50210","50000","50000","50000","50300","50000","50300","50230","50230","50230","50120","50120","50230","50230","50230","50120","50120","57000","57000","57000","57100","57000","57000","57100","57100","57000","57000","57100","57100","57000","57100","57000","57000","57100","57100","57100","57100","57000","40000","40000","40000","40000","40000","40000","40000","40000","40000","40000","40000","40000","40000","20000","20000","20000","20000","20000","20130","20000","20000","20000","20000","20000","20000","20000","20130","20000"];
        $address_1 = ["54 ม.6  ถ.ลี่ยงเมือง 216 ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","353/2 ถ.มิตรภาพ ต.โนนสูง อ.เมือง จ.อุดรธานี 41000","เลขที่ 54 ม.6  1 ซ.กำนัน ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 333/17 ม.5  ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 174/1 ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 86/3 ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","ถนนนิตโย ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","หลังโรงแรมบ้านเชียง ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 603 ม.4 ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 497  ซ.หนองบัว ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","เลขที่ 183/3 ม.1 บ้านหนองบัว ต.หมากแข้ง อ.เมือง จ.อุดรธานี 41000","1442 ซ.ท้าวสุระ ต.หัวทะเล อ.เมือง จ.นครราชสีมา","1995/5 ถ.สืบศิริ ต.ในเมือง อ.เมือง จ.นครราชสีมา","1105 ต.สุรนารี อ.เมือง จ.นครราชสีมา","1167 ถ.สุรนารายณ์ ต.ในเมือง อ.เมือง จ.นครราชสีมา","ถนนสืบศิริ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","111/2 ต.ในเมือง อ.เมือง จ.นครราชสีมา","ถนนสืบศิริ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","หมู่บ้านสุรสวัสดิ์แลนด์ ถ.มหาวิทยาลัยประตู 1 ต.สุรนารี อ.เมือง จ.นครราชสีมา 30000","เถลิงพลซอย6 ต.สุรนารี อ.เมือง จ.นครราชสีมา","275/1 ถ.มหาวิทยาลัย ต.สุรนารี อ.เมือง จ.นครราชสีมา","ซ.สวายเรียง ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","ซ.สืบศิริ24 ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","โครงการThe Link Condo ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","333/6 ม.15 ต.จอหอ อ.เมือง จ.นครราชสีมา 30000","1449 ซ.บุญวัฒนา4 ต.หัวทะเล อ.เมือง จ.นครราชสีมา 30000","935 ม.1 ต.สุรนารี อ.เมือง จ.นครราชสีมา","ถ.จอมสุรางยาตร์ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000","75/327 ม.11 ต.โคกกรวด อ.เมือง จ.นครราชสีมา 30000","มทส.ประตู4 ต.โพธิ์กลาง อ.เมือง จ.นครราชสีมา 30000","มทส.ประตู4  ต. สุรนารี อ.เมือง จ.นครราชสีมา 30000","เลขที่ 68 ม.8 ต.บ้านใหม่ อ.เมือง จ.นครราชสีมา 30000","เลขที่ 55/1 ตำบลป่าตัน อำเภอเมือง จังหวัดเชียงใหม่ 50000","โครงการสันติธรรมพลาซ่า ตำบลช้างเผือก อำเภอเมือง จังหวัดเชียงใหม่ 50000","เลขที่ 55  ต.วัดเกต อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50000","เลขที่ 40 ตำบลป่าตัน อำเภอเมือง จังหวัดเชียงใหม่ 50000","อาคารจอดรถมหาวิทยาลัยเชียงใหม่ CMU S1 ชั้น 1  ต.สุเทพ อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50200","ตำบลช้างเผือก อำเภอเมือง จังหวัดเชียงใหม่ 50000","อาคารจอดรถมหาวิทยาลัยเชียงใหม่ CMU S1 ชั้น 1  ต.สุเทพ อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50200","โครงการสันติธรรมพลาซ่า ตำบลช้างเผือก อำเภอเมือง จังหวัดเชียงใหม่ 50000","เลขที่ 75/55 หมู่ที่ 1 ตำบลท่าศาลา  อำเภอเมืองเชียงใหม่  จังหวัดเชียงใหม่ 50130","เลขที่ 17  หมู่ที่ 6 ตำบลสันทรายหลวง อำเภอสันทราย จังหวัดเชียงใหม่ 50210","เลขที่ 373/574  ต.หนองหอย อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50000","เลขที่ 334/102 หมู่ที่ 1 ตำบลแม่เหียะ  อำเภอเมืองเชียงใหม่  จังหวัดเชียงใหม่ 50000","โรงพยาบาลสวนดอก ตำบลสุเทพ อำเภอเมืองเชียงใหม่  จังหวัดเชียงใหม่ 50000","เลขที่ 271 ตำบลช้างเผือก อำเภอเมืองเชียงใหม่ จังหวัดเชียงใหม่ 50300","เลขที่ 2/1 ตำบลหายยา อำเภอเมืองเชียงใหม่  จังหวัดเชียงใหม่ 50000","เลขที่ 49 หมู่ 7 ต.ร้องวัวแดง อ.สันกำแพง จ.เชียงใหม่ 50300","เลขที่ 197 ตำบลหนองควาย อำเภอหางดง จังหวัดเชียงใหม่ 50230","เลขที่ 323/63 หมู่ที่ 2 ตำบลสันผักหวาน อำเภอหางดง จังหวัดเชียงใหม่ 50230","เลขที่ 47/2 ตำบลหารแก้ว อำเภอหางดง จังหวัดเชียงใหม่ 50230","ตำบลยุหว่า อำเภอสันป่าตอง จังหวัดเชียงใหม่  50120","เลขที่ 491 ตำบลยุหว่า อำเภอสันป่าตอง จังหวัดเชียงใหม่ 50120","เลขที่ 121/1  ตำบลหางดงอำเภอหางดง จังหวัดเชียงใหม่ 50230","เลขที่ 201 หมู่ที่ 1 ตำบลหนองแก๋ว อำเภอหางดง จังหวัดเชียงใหม่ 50230","เลขที่ 153 หมู่ที่.4 ตำบลบ้านแหวน อำเภอหางดง จังหวัดเชียงใหม่ 50230","ตำบลต้นแหน อำเภอสันป่าตอง จังหวัดเชียงใหม่ 50120","เลขที่ 635 หมู่ที่ 3 ตำบลบ้านกลาง อำเภอสันป่าตอง จังหวัดเชียงใหม่ 50120","เลขที่ 256/87 หมู่ที่ 15 ตำบลรอบเวียง อำเภอเมืองเชียงราย จังหวัดเชียงราย 57000","เลขที่ 557/2  โครงการตลาด หมู่ที่ 13 ต.รอบเวียง อ.เมือง จ.ชียงราย 57000","370/1 ม.9 ต.บ้านดู่ อ.เมือง จ.เชียงราย 57000","เลขที่ 179  โครงการตลาด หมู่ที่ 9 ตำบล บ้านดู่ อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่ 199/8 โครงการตลาด หมู่ที่ 15 ตำบลรอบเวียง อำเภอเมือง จังหวัดเชียงราย 57000","เลขที่ 255 โครงการตลาด หมู่ที่ 16 ตำบลรอบเวียง อำเภอเมือง จังหวัดเชียงราย 57000","เลขที่ 514/87 โครงการตลาด หมู่ที่ 1 ตำบลท่าสุด อำเภอเมืองเชียงราย จังหวัดเชียงราย 57100","เลขที่ 621 โครงการตลาด หมู่ที่ 1 ตำบลท่าสุด อำเภอเมืองเชียงราย จังหวัดเชียงราย 57100","เลขที่ 329/186  โครงการตลาด หมู่ที่ 4 ตำบล รอบเวียง อำเภอเมือง จังหวัดเชียงราย 57000","เลขที่ 82 โครงการตลาด หมู่ที่ 1 ตำบลเวียง อำเภอเมือง จังหวัดเชียงราย 57000","เลขที่ 121/60  โครงการตลาด  หมู่ที่ 3 ตำบล บ้านดู่ อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่ 235   ตำบล บ้านดู่ อำเภอเมืองเชียงราย จังหวัดเชียงราย 57100","เลขที่ 117 โครงการตลาด ต.รอบเวียง อ.เมือง จ.ชียงราย 57000","เลขที่ 468/12-13  โครงการตลาด  หมู่ที่ 3 ตำบล บ้านดู่ อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่ 6 หมู่ 22   ตำบล แม่ข้าวต้ม อำเภอเมืองเชียงราย จังหวัดเชียงราย 57000","เลขที่ 109 หมู่ 23   ตำบล รอบเวียง อำเภอเมืองเชียงราย จังหวัดเชียงราย 57000","เลขที่ 168/33   ตำบล บ้านดู่ อำเภอเมืองเชียงราย จังหวัดเชียงราย 57100","เลขที่ 260/5 โครงการตลาด หมู่ที่ 5 ตำบลบ้านดู่ อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่   โครงการตลาด  หมู่ที่  ตำบล ท่าสุด อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่ 203  โครงการตลาด  หมู่ที่ 4 ตำบล ริมกก อำเภอเมือง จังหวัดเชียงราย 57100","เลขที่ 264/3 หมู่ 9   ตำบล รอบเวียง อำเภอเมืองเชียงราย จังหวัดเชียงราย 57000","8  สาวะถี อ.เมือง จ.ขอนแก่น","384  บ้านโนนม่วง  ต.ศิลา อ.เมือง จ.ขอนแก่น","623 ม1 บ้านไผ่ เมือง ขอนแก่น","104  ม.13  บ้านโคกสี  อ.เมือง จ.ขอนแก่น","144/78  ต.เมืองเก่า อ.เมือง จ.ขอนแกน","1/6 รอบบึง ต.ในเมือง อ.เมือง จ.ขอนแก่น  40000","บ้านโนนม่วง ถนนมิรภาพ อ.เมือง จ.ขอนแก่น","122 ม.13 รอบบึงทุ่งสร้าง เมือง ขอนแก่น","161  ม 3 บ้านโนนม่วง ต.ศิลา อ.เมือง จ.ขอนแก่น","123/16 อ.เมือง จ.ขอนแก่น 40000","48/110 ถนนเสรีสัมพันธ์  ตำบลในเมือง  อำเภอเมือง ขอนแก่น 40000","20/3 ในเมือง ขอนแก่น","100/532  ต.ศิลา อ.เมือง จ.ขอนแก่น","เลขที่203/23 ตำบลบ้านบึง  อำเภอเมือง จังหวัดชลบุรี 20000","ถนน 344 ชลบุรี-บ้านบึง ชลบุรี ตำบลบ้านบึง อำเภอเมือง จังหวัดชลบุรี 20000","เลขที่ 47 ตำบลคลองตำหรุ  อำเภอเมือง จังหวัดชลบุรี 20000","เลขที่209/2526  หมู่ที่ 3 ตำบลเสม็ด อำเภอเมือง จังหวัดชลบุรี 20000","ต.เสม็ด อ เมือง ชลบรี 20000","เลขที่ 14/3 ตำบลแสนสุข อำเภอเมืองชลบุรี จังหวัดชลบุรี 20130","เลขที่ 94/3 ถนนบางแสนล่าง ตำบลแสนสุข อำเภอเมือง จังหวัดชลบุรี 20000","ตำบลดอนหัวฬ่อ อำเภอเมือง  จังหวัดชลบุรี 20000","เลขที่ 225/180 ตำบลบ้านสวน อำเภอเมือง จังหวัดชลบุรี 20000","เลขที่ 11 ตำบลบ้านสวน อำเภอเมืองชลบุรี จังหวัดชลบุรี 20000","เลขที่ 73/23 ม.8 ตำบลบ้านสวน อำเภอเมืองชลบุรี 20000","ตลาดโต้รุ่งหน้าศาล ตำบลบางปลาสร้อย อำเภอเมืองชลบุรี   จังหวัดชลบุรี 20000","เลขที่ 29/6 หมู่ที่ 6 ถนนมิตรสัมพันธ์ ตำบลบ้านปึก อำเภอเมือง จังหวัดชลบุรี 20000","เลขที่ 3 ตำบลแสนสุข อำเภอเมือง จังหวัดชลบุรี 20130","เลขที่98/23 ตำบลเสม็ด อำเภอเมืองชลบุรี  จังหวัดชลบุรี 20000"];
        
      
        for ($i=0; $i < sizeof($name); $i++) { 
            # code...
            $phone_number = str_replace( '-', '' , $phone[$i] );
            $newcustomer = new Customer;
            $newcustomer->name  =  $name[$i];
            $newcustomer->password  =  bcrypt($phone_number);
            $newcustomer->phone  =  $phone_number;
            $newcustomer->zipcode  =  $zipcode[$i];
            $newcustomer->role = "premium" ; 
            $newcustomer->status = 1 ; 

            
            if($newcustomer->save()){
                $address = new Address;
                $address->alias = $name[$i];
                $address->address_1 = $address_1[$i];
                $address->country_id = 211 ;
                $address->phone = $phone_number;
                $newcustomer->zip  =  $zipcode[$i];
                $address->customer_id = $newcustomer->id;
                $address->status = 1 ; 
                $address->save(); 
            }
        }
    }
}
