package dbFolder;

import beanFolder.OperatorsBus;
import java.util.ArrayList;

public class OperatorsBusDb{
	ArrayList<OperatorsBus> operatorsBus=new ArrayList<>();
	
	public void add(int id,String name,String regNo){
		operatorsBus.add(new OperatorsBus(id,name,regNo));
	}
}
	